<?php

use App\Filament\Resources\Projects\Pages\EditProject;
use App\Models\Dokploy;
use App\Models\Project;
use App\Models\User;
use App\Services\DeployService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create());

    $dokploy = Dokploy::create([
        'base_url' => 'https://dokploy.example.test',
        'token' => 'test-token',
    ]);

    $this->project = Project::create([
        'dokploy_id' => $dokploy->id,
        'app_name' => 'example',
        'dokploy_project_id' => 'project-id',
        'github_id' => 'github-id',
        'github_owner' => 'example',
        'github_repository' => 'app',
        'compose_name_file' => 'compose.yml',
        'domain_name' => 'example.test',
        'extra_sub_domains' => ['api', 'admin'],
        'service_name' => 'server',
        'environment_staging' => 'BRANCH={BRANCH}',
    ])->fresh();
});

it('defaults projects and the edit form to letsencrypt', function (): void {
    expect($this->project->certificate_type)->toBe('letsencrypt')
        ->and($this->project->custom_cert_resolver)->toBe('infomaniak');

    Livewire::test(EditProject::class, ['record' => $this->project->getRouteKey()])
        ->assertFormSet(['certificate_type' => 'letsencrypt'])
        ->assertFormFieldIsHidden('custom_cert_resolver')
        ->fillForm(['certificate_type' => 'custom'])
        ->assertFormFieldIsVisible('custom_cert_resolver')
        ->assertFormSet(['custom_cert_resolver' => 'infomaniak']);
});

it('saves the TLS choice and uses it for every new staging domain', function (string $certificateType, string $resolver): void {
    // Start with the opposite choice to verify switching providers as well.
    $this->project->update([
        'certificate_type' => $certificateType === 'letsencrypt' ? 'custom' : 'letsencrypt',
    ]);

    Livewire::test(EditProject::class, ['record' => $this->project->getRouteKey()])
        ->fillForm(['certificate_type' => $certificateType, 'custom_cert_resolver' => $resolver])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->project->fresh()->certificate_type)->toBe($certificateType);

    if ($certificateType === 'custom') {
        expect($this->project->fresh()->custom_cert_resolver)->toBe($resolver);
    }

    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        $data = match (true) {
            str_contains($request->url(), 'environment.create') => ['environmentId' => 'environment-id'],
            str_contains($request->url(), 'compose.create') => ['composeId' => 'compose-id'],
            str_contains($request->url(), 'compose.getDefaultCommand') => 'docker compose up -d',
            default => [],
        };

        return Http::response([['result' => ['data' => ['json' => $data]]]]);
    });

    $staging = app(DeployService::class)->deploy($this->project->fresh(), 'create', '42', 'main');

    expect($staging?->compose_id)->toBe('compose-id');

    $requests = Http::recorded(fn (Request $request): bool => str_contains($request->url(), 'domain.create'));
    expect($requests)->toHaveCount(3);

    $domains = $requests->map(fn (array $record): array => $record[0]->data()['0']['json']);
    expect($domains->pluck('host')->all())->toBe([
        'staging-42.example.test',
        'api.staging-42.example.test',
        'admin.staging-42.example.test',
    ]);

    foreach ($domains as $domain) {
        expect($domain['https'])->toBeTrue()
            ->and($domain['composeId'])->toBe('compose-id')
            ->and($domain['serviceName'])->toBe('server')
            ->and($domain['certificateType'])->toBe($certificateType);

        if ($certificateType === 'custom') {
            expect($domain['customCertResolver'])->toBe($resolver);
        } else {
            expect($domain)->not->toHaveKey('customCertResolver');
        }
    }
})->with([
    'letsencrypt' => ['letsencrypt', 'infomaniak'],
    'custom infomaniak' => ['custom', 'infomaniak'],
    'custom resolver' => ['custom', 'another-resolver'],
]);

it('rejects unsupported TLS choices in the edit form', function (): void {
    Livewire::test(EditProject::class, ['record' => $this->project->getRouteKey()])
        ->fillForm(['certificate_type' => 'unsupported'])
        ->call('save')
        ->assertHasFormErrors(['certificate_type']);

    expect($this->project->fresh()->certificate_type)->toBe('letsencrypt');
});

it('requires a resolver when using custom TLS', function (): void {
    Livewire::test(EditProject::class, ['record' => $this->project->getRouteKey()])
        ->fillForm(['certificate_type' => 'custom', 'custom_cert_resolver' => ''])
        ->call('save')
        ->assertHasFormErrors(['custom_cert_resolver' => 'required']);

    expect($this->project->fresh()->certificate_type)->toBe('letsencrypt');
});
