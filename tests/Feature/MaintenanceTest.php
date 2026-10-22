<?php

use App\Exceptions\MaintenanceException;
use App\Models\Dokploy;
use App\Models\Project;
use App\Services\MaintenanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    $this->dokploy = Dokploy::create([
        'name' => 'Production',
        'base_url' => 'https://dokploy.example.test',
        'token' => 'private-api-token',
    ]);
    $this->project = Project::create([
        'app_name' => 'Site de production',
        'dokploy_id' => $this->dokploy->id,
        'maintenance_yml_file' => 'site-maintenance.yml',
    ])->fresh();
    $this->service = app(MaintenanceService::class);
    $this->content = "{{ \$maintenance_enabled := 0 }}\nhttp:\n  routers:\n    app: {}\n";
});

it('reads the actual flag without modifying Traefik', function (string $flag, bool $expected): void {
    Http::fake(['*/settings.readTraefikFile*' => Http::response(json_encode(str_replace(':= 0', ':= '.$flag, $this->content)))]);

    expect($this->service->refresh($this->project))->toBe($expected)
        ->and($this->project->fresh()->maintenance_enabled)->toBe($expected)
        ->and($this->project->maintenance_checked_at)->not->toBeNull();

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->hasHeader('x-api-key', 'private-api-token')
        && $request['path'] === '/etc/dokploy/traefik/dynamic/site-maintenance.yml'
        && ! isset($request['serverId']));
})->with([['0', false], ['1', true]]);

it('changes only the maintenance flag and verifies the entire file on the correct server', function (bool $enabled, ?string $server, string $file): void {
    $this->project->update(['server_id' => $server, 'maintenance_yml_file' => $file]);
    $before = str_replace(':= 0', $enabled ? ':= 0' : ':= 1', $this->content);
    $after = str_replace(':= 0', ':= '.(int) $enabled, $this->content);
    $path = str_starts_with($file, '/') ? $file : '/etc/dokploy/traefik/dynamic/'.$file;
    Http::fake([
        '*/settings.readTraefikFile*' => Http::sequence()->push(json_encode($before))->push(json_encode(str_replace("\n", "\r\n", $after)."\r\n")),
        '*/settings.updateTraefikFile' => Http::response(['success' => true]),
    ]);

    expect($this->service->setEnabled($this->project, $enabled))->toBe($enabled)
        ->and($this->project->fresh()->maintenance_enabled)->toBe($enabled)
        ->and($this->project->maintenance_error)->toBeNull();

    Http::assertSentCount(3);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->data() === array_filter(['path' => $path, 'serverId' => $server, 'traefikConfig' => $after], fn ($value): bool => $value !== null));
})->with([
    'activate locally' => [true, null, 'site-maintenance.yml'],
    'restore on a remote server' => [false, 'remote-server', '/custom/traefik/site.yaml'],
]);

it('does not rewrite the file when it already has the requested state', function (): void {
    Http::fake(['*' => Http::response(json_encode($this->content))]);
    expect($this->service->setEnabled($this->project, false))->toBeFalse();
    Http::assertSentCount(1);
});

it('refuses a missing or ambiguous flag and never writes the file', function (string $content): void {
    Http::fake(['*' => Http::response(json_encode($content))]);
    expect(fn () => $this->service->setEnabled($this->project, true))->toThrow(MaintenanceException::class, 'absente ou ambiguë');
    Http::assertSentCount(1);
    expect($this->project->fresh()->maintenance_enabled)->toBeNull()
        ->and($this->project->maintenance_error)->toContain('absente ou ambiguë');
})->with([
    'missing' => ['http: {}'],
    'ambiguous' => ['{{ $maintenance_enabled := 0 }} {{ $maintenance_enabled := 1 }}'],
    'unsupported value' => ['{{ $maintenance_enabled := 2 }}'],
]);

it('rejects non-string API responses', function (): void {
    Http::fake(['*' => Http::response(['content' => $this->content])]);
    expect(fn () => $this->service->refresh($this->project))->toThrow(MaintenanceException::class, 'ne contient pas le texte');
    Http::assertSentCount(1);
});

it('keeps the state unknown when the full written content cannot be verified', function (): void {
    $this->project->update(['maintenance_enabled' => false, 'maintenance_checked_at' => now()]);
    $different = str_replace(':= 0', ':= 1', $this->content).'# changed concurrently';
    Http::fake([
        '*/settings.readTraefikFile*' => Http::sequence()->push(json_encode($this->content))->push(json_encode($different))->push(json_encode($different))->push(json_encode($different)),
        '*/settings.updateTraefikFile' => Http::response([]),
    ]);

    expect(fn () => $this->service->setEnabled($this->project, true))->toThrow(MaintenanceException::class, 'L’écriture n’est pas confirmée');
    expect($this->project->fresh()->maintenance_enabled)->toBeNull()
        ->and($this->project->maintenance_checked_at)->toBeNull()
        ->and($this->project->maintenance_error)->not->toBeNull();
    Http::assertSentCount(5);
});

it('retries verification when Dokploy initially returns the old file', function (): void {
    Http::fake([
        '*/settings.readTraefikFile*' => Http::sequence()->push(json_encode($this->content))->push(json_encode($this->content))->push(json_encode(str_replace(':= 0', ':= 1', $this->content))),
        '*/settings.updateTraefikFile' => Http::response([]),
    ]);
    expect($this->service->setEnabled($this->project, true))->toBeTrue();
    Http::assertSentCount(4);
});

it('reports HTTP failures without disclosing response bodies or tokens', function (string $operation): void {
    Http::fake([
        '*/settings.readTraefikFile*' => $operation === 'read' ? Http::response('private-api-token', 403) : Http::response(json_encode($this->content)),
        '*/settings.updateTraefikFile' => Http::response('private-api-token', 403),
    ]);
    expect(fn () => $this->service->setEnabled($this->project, true))->toThrow(MaintenanceException::class, 'HTTP 403');
    expect($this->project->fresh()->maintenance_error)->not->toContain('private-api-token')
        ->and($this->project->maintenance_enabled)->toBeNull();
})->with(['read', 'write']);

it('handles connection failures and releases the file lock', function (): void {
    Http::fake(['*' => Http::sequence()->pushFailedConnection()->push(json_encode($this->content))]);
    expect(fn () => $this->service->refresh($this->project))->toThrow(MaintenanceException::class, 'Connexion');
    expect($this->service->refresh($this->project))->toBeFalse();
});

it('rejects unsafe or unsupported paths before calling Dokploy', function (string $path): void {
    $this->project->update(['maintenance_yml_file' => $path]);
    expect(fn () => $this->service->refresh($this->project))->toThrow(MaintenanceException::class);
    Http::assertNothingSent();
})->with(['../site.yml', '/etc/../site.yml', 'site.json', '', "site\n.yml"]);

it('normalizes an instance URL ending in api', function (): void {
    $this->dokploy->update(['base_url' => 'https://dokploy.example.test/api/']);
    Http::fake(['https://dokploy.example.test/api/settings.readTraefikFile*' => Http::response(json_encode($this->content))]);
    expect($this->service->refresh($this->project))->toBeFalse();
});

it('invalidates the cached state when maintenance configuration or credentials change', function (string $field): void {
    $this->project->update(['maintenance_enabled' => true, 'maintenance_checked_at' => now()]);
    match ($field) {
        'file' => $this->project->update(['maintenance_yml_file' => 'other.yml']),
        'server' => $this->project->update(['server_id' => 'other-server']),
        'token' => $this->dokploy->update(['token' => 'replacement']),
    };
    $this->project->refresh();
    expect($this->project->maintenance_enabled)->toBeNull()
        ->and($this->project->maintenance_checked_at)->toBeNull();
})->with(['file', 'server', 'token']);

it('does not allow concurrent operations on the same file', function (): void {
    $path = $this->service->resolvePath($this->project->maintenance_yml_file);
    $key = app(\App\Services\DokployTraefikService::class)->lockKey($this->project, $path);
    $lock = Cache::lock($key, 180);
    $lock->get();
    try {
        expect(fn () => $this->service->setEnabled($this->project, true))->toThrow(MaintenanceException::class, 'déjà en cours');
        Http::assertNothingSent();
    } finally {
        $lock->release();
    }
});
