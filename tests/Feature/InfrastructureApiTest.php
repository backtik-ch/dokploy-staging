<?php

use App\Models\Dokploy;
use App\Models\Project;
use App\Models\Staging;
use App\Models\User;
use App\Services\Infrastructure\Operations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

function infraAuthenticate($test, string $token): void
{
    app('auth')->forgetGuards();
    $test->withToken($token);
}

beforeEach(function (): void {
    Http::preventStrayRequests();
    $this->user = User::factory()->create();
    $this->adminToken = $this->user->createToken('admin', ['*']);
    infraAuthenticate($this, $this->adminToken->plainTextToken);
    $this->connection = Dokploy::create(['name' => 'Production', 'base_url' => 'https://dokploy.example.test', 'token' => 'secret-dokploy']);
    $this->project = Project::create([
        'dokploy_id' => $this->connection->id, 'app_name' => 'Example', 'dokploy_project_id' => 'remote-project',
        'github_id' => 'github', 'github_owner' => 'backtik', 'github_repository' => 'example', 'github_token' => 'secret-github',
        'compose_name_file' => 'compose.yml', 'domain_name' => 'example.test', 'environment_staging' => 'PASSWORD=secret-env',
    ])->refresh();
});

it('requires valid unexpired tokens and JSON errors even without an accept header', function (): void {
    foreach (['invalid-token', $this->user->createToken('expired', ['*'], now()->subMinute())->plainTextToken] as $token) {
        infraAuthenticate($this, $token);
        $this->get('/api/v1/projects')->assertUnauthorized()->assertJsonPath('message', 'Unauthenticated.');
    }
});

it('lists and filters projects with bounded pagination and redacts secrets throughout CRUD', function (): void {
    $this->getJson('/api/v1/projects?search=Exam&per_page=1')->assertOk()->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.github_token_configured', true)->assertJsonMissingPath('data.0.github_token')
        ->assertJsonMissingPath('data.0.environment_staging')->assertDontSee('secret-github');
    $this->getJson('/api/v1/connections/'.$this->connection->id)->assertOk()->assertJsonMissingPath('data.token')->assertDontSee('secret-dokploy');
    $this->getJson('/api/v1/projects?per_page=101')->assertUnprocessable()->assertJsonValidationErrors('per_page');
    $created = $this->postJson('/api/v1/projects', ['app_name' => 'New project', 'dokploy_id' => $this->connection->id])
        ->assertCreated()->assertJsonPath('data.staging_configured', false)->json('data.id');
    $this->patchJson('/api/v1/projects/'.$created, ['app_name' => 'Renamed'])->assertOk()->assertJsonPath('data.app_name', 'Renamed');
    $this->deleteJson('/api/v1/projects/'.$created)->assertOk()->assertJsonPath('data.deleted', true);
    $this->getJson('/api/v1/projects/'.$created)->assertNotFound();
});

it('supports connection CRUD without exposing the token and refuses dangling projects', function (): void {
    $id = $this->postJson('/api/v1/connections', ['name' => 'Staging', 'base_url' => 'https://staging.example.test', 'token' => 'new-secret'])
        ->assertCreated()->assertJsonMissingPath('data.token')->assertDontSee('new-secret')->json('data.id');
    $this->patchJson('/api/v1/connections/'.$id, ['token' => 'replacement'])->assertOk()->assertDontSee('replacement');
    $this->deleteJson('/api/v1/connections/'.$id)->assertOk();
    $this->deleteJson('/api/v1/connections/'.$this->connection->id)->assertConflict();
    $this->postJson('/api/v1/projects', ['app_name' => 'Bad', 'dokploy_id' => $id])->assertUnprocessable();
});

it('returns native resource pagination links that preserve filters and protect secrets on every page', function (): void {
    Project::create(['app_name' => 'Example second', 'dokploy_id' => $this->connection->id, 'github_token' => 'second-secret']);
    Project::create(['app_name' => 'Unrelated', 'dokploy_id' => $this->connection->id]);
    $first = $this->getJson('/api/v1/projects?search=Example&per_page=1')->assertOk()
        ->assertJsonPath('meta.total', 2)->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('data.0.id', $this->project->id)->assertJsonMissingPath('data.data');
    $next = $first->json('links.next');
    parse_str(parse_url($next, PHP_URL_QUERY), $query);
    expect($query)->toMatchArray(['search' => 'Example', 'per_page' => '1', 'page' => '2']);
    expect($first->headers->get('Cache-Control'))->toContain('no-store');
    $this->getJson($next)->assertOk()->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('data.0.app_name', 'Example second')->assertJsonPath('links.next', null)
        ->assertJsonMissingPath('data.0.github_token')->assertDontSee('second-secret');
});

it('enforces abilities for management and all legacy mutation routes', function (): void {
    infraAuthenticate($this, $this->user->createToken('reader', ['infra:read', 'logs:read'])->plainTextToken);
    $this->getJson('/api/v1/projects')->assertOk();
    foreach ([['POST', '/api/v1/projects'], ['POST', '/api/deploy'], ['DELETE', '/api/remove'], ['POST', '/api/dependencies'], ['POST', '/api/v1/tokens']] as [$method, $url]) {
        $this->json($method, $url)->assertForbidden();
    }
    infraAuthenticate($this, $this->user->createToken('writer', ['infra:write'])->plainTextToken);
    $this->patchJson('/api/v1/projects/'.$this->project->id, ['github_token' => 'override'])->assertForbidden();
    $this->patchJson('/api/v1/projects/'.$this->project->id, ['maintenance_yml_file' => 'test.yml'])->assertForbidden();
    $this->patchJson('/api/v1/projects/'.$this->project->id, ['app_name' => 'Allowed'])->assertOk();
    Http::assertNothingSent();
});

it('enforces model policies as well as token abilities', function (): void {
    Gate::before(fn ($user, $ability) => $ability === 'update' ? false : null);
    $this->patchJson('/api/v1/projects/'.$this->project->id, ['app_name' => 'Forbidden'])->assertForbidden();
    expect($this->project->refresh()->app_name)->toBe('Example');
});

it('validates nested settings and rejects unknown or server owned fields', function (): void {
    $this->patchJson('/api/v1/projects/'.$this->project->id.'/settings', ['service_port' => 70000])->assertUnprocessable();
    $this->patchJson('/api/v1/projects/'.$this->project->id.'/settings', ['linked_repositories' => [['label' => 'Missing rest']]])->assertUnprocessable();
    $this->patchJson('/api/v1/projects/'.$this->project->id, ['maintenance_enabled' => true])->assertUnprocessable();
    $this->patchJson('/api/v1/projects/'.$this->project->id.'/settings', ['service_port' => 8080, 'linked_repositories' => [
        ['label' => 'Frontend', 'owner' => 'backtik', 'repository' => 'frontend', 'branch_placeholder' => 'FRONTEND_BRANCH', 'wait_for_image' => false],
    ]])->assertOk()->assertJsonPath('data.service_port', 8080);
    $this->deleteJson('/api/v1/projects/'.$this->project->id.'/settings')->assertOk()->assertJsonPath('data.staging_configured', false)
        ->assertJsonPath('data.environment_staging_configured', false);
});

it('creates updates reads and deletes stagings through the real deployment services', function (): void {
    Http::fake(function ($request) {
        $data = match (true) {
            str_contains($request->url(), 'environment.create') => ['environmentId' => 'environment'],
            str_contains($request->url(), 'compose.create') => ['composeId' => 'compose'],
            str_contains($request->url(), 'compose.getDefaultCommand') => 'docker compose up -d',
            default => [],
        };

        return Http::response([['result' => ['data' => ['json' => $data]]]]);
    });
    $id = $this->postJson('/api/v1/projects/'.$this->project->id.'/stagings', ['staging_reference' => '42', 'branch' => 'feature/test'])
        ->assertAccepted()->assertJsonPath('data.deploy_status', 'waiting_for_images')->assertJsonMissingPath('data.environment')->json('data.id');
    $this->getJson('/api/v1/stagings?project_id='.$this->project->id)->assertOk()->assertJsonPath('meta.total', 1);
    $this->getJson('/api/v1/stagings/'.$id)->assertOk()->assertJsonPath('data.staging_reference', '42');
    $this->patchJson('/api/v1/stagings/'.$id, ['branch' => 'main'])->assertAccepted()->assertJsonPath('data.branch', 'main');
    $this->postJson('/api/v1/stagings/'.$id.'/redeploy')->assertAccepted();
    $this->patchJson('/api/v1/projects/'.$this->project->id, ['server_id' => 'another'])->assertConflict();
    $this->deleteJson('/api/v1/projects/'.$this->project->id)->assertConflict();
    $this->deleteJson('/api/v1/projects/'.$this->project->id.'/settings')->assertConflict();
    $this->deleteJson('/api/v1/stagings/'.$id)->assertOk();
    $this->assertSoftDeleted('stagings', ['id' => $id]);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'compose.delete') && $request->data()['0']['json']['deleteVolumes'] === true);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'environment.remove'));
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'compose.deploy'));
});

it('retains the staging on a remote deletion failure and redacts deployment errors', function (): void {
    $staging = Staging::create(['project_id' => $this->project->id, 'pr_number' => 'test', 'branch' => 'main', 'compose_id' => 'compose', 'environment_id' => 'env', 'environment' => 'secret-env']);
    Http::fake(['*' => Http::response('secret-dokploy secret-env', 403)]);
    $this->deleteJson('/api/v1/stagings/'.$staging->id)->assertStatus(502)->assertDontSee('secret-dokploy');
    expect($staging->refresh()->deleted_at)->toBeNull();
    $this->postJson('/api/v1/stagings/'.$staging->id.'/redeploy')->assertStatus(502)->assertDontSee('secret-env');
});

it('configures reads sets and removes maintenance with remote verification', function (): void {
    $content = '{{ $maintenance_enabled := 0 }}';
    Http::fake(function ($request) use (&$content) {
        if ($request->method() === 'POST') {
            $content = $request['traefikConfig'];

            return Http::response(['success' => true]);
        }

        return Http::response(json_encode($content));
    });
    $url = '/api/v1/projects/'.$this->project->id.'/maintenance';
    $this->getJson($url)->assertOk()->assertJsonPath('data.maintenance_enabled', null);
    $this->putJson($url, ['maintenance_yml_file' => '../bad.yml'])->assertUnprocessable();
    $this->putJson($url, ['enabled' => true])->assertUnprocessable();
    $this->putJson($url, ['maintenance_yml_file' => 'site.yml', 'enabled' => true])->assertOk()->assertJsonPath('data.maintenance_enabled', true);
    $this->getJson($url)->assertOk()->assertJsonPath('data.maintenance_enabled', true);
    $this->putJson($url, ['maintenance_yml_file' => 'other.yml'])->assertConflict();
    $this->deleteJson($url)->assertOk()->assertJsonPath('data.maintenance_yml_file', null);
    expect($content)->toContain(':= 0');
});

it('does not clear maintenance configuration after an unconfirmed remote operation', function (): void {
    $this->project->update(['maintenance_yml_file' => 'site.yml']);
    Http::fake(['*' => Http::response('secret-dokploy', 403)]);
    $this->deleteJson('/api/v1/projects/'.$this->project->id.'/maintenance')->assertStatus(502)->assertDontSee('secret-dokploy');
    expect($this->project->refresh()->maintenance_yml_file)->toBe('site.yml');
});

it('issues lists edits rotates and revokes tokens with secrets returned only once', function (): void {
    $created = $this->postJson('/api/v1/tokens', ['name' => 'Reader', 'abilities' => ['infra:read']])->assertCreated();
    $id = $created->json('data.id');
    $old = $created->json('data.plain_text_token');
    expect($created->json('data.expires_at'))->not->toBeNull();
    $this->getJson('/api/v1/tokens')->assertOk()->assertJsonMissingPath('data.0.token')->assertDontSee($old);
    $this->patchJson('/api/v1/tokens/'.$id, ['name' => 'Renamed'])->assertOk()->assertJsonPath('data.name', 'Renamed')->assertJsonMissingPath('data.plain_text_token');
    $rotated = $this->postJson('/api/v1/tokens/'.$id.'/rotate')->assertCreated();
    $new = $rotated->json('data.plain_text_token');
    $newId = $rotated->json('data.id');
    infraAuthenticate($this, $old);
    $this->getJson('/api/v1/projects')->assertUnauthorized();
    infraAuthenticate($this, $new);
    $this->getJson('/api/v1/projects')->assertOk();
    infraAuthenticate($this, $this->adminToken->plainTextToken);
    $this->deleteJson('/api/v1/tokens/'.$newId)->assertOk();
    infraAuthenticate($this, $new);
    $this->getJson('/api/v1/projects')->assertUnauthorized();
});

it('prevents token privilege escalation lifetime extension and access to other users tokens', function (): void {
    $caller = $this->user->createToken('delegator', ['infra:read', 'tokens:manage'], now()->addHour());
    infraAuthenticate($this, $caller->plainTextToken);
    $this->postJson('/api/v1/tokens', ['name' => 'Escalation', 'abilities' => ['*']])->assertForbidden();
    $this->postJson('/api/v1/tokens', ['name' => 'Escalation', 'abilities' => ['infra:write']])->assertForbidden();
    $this->postJson('/api/v1/tokens', ['name' => 'Too long', 'expires_at' => now()->addDay()->toIso8601String()])->assertUnprocessable();
    $created = $this->postJson('/api/v1/tokens', ['name' => 'Bounded'])->assertCreated();
    expect(Carbon\CarbonImmutable::parse($created->json('data.expires_at'))->timestamp)->toBe($caller->accessToken->expires_at->timestamp);
    $this->postJson('/api/v1/tokens/'.$this->adminToken->accessToken->id.'/rotate')->assertForbidden();
    $other = User::factory()->create()->createToken('Someone else', ['infra:read']);
    $this->getJson('/api/v1/tokens/'.$other->accessToken->id)->assertNotFound();
    $this->deleteJson('/api/v1/tokens/'.$other->accessToken->id)->assertNotFound();
});

it('searches Loki with literal filters and discovers labels and values', function (): void {
    config(['loki.url' => 'https://loki.example.test', 'loki.token' => 'loki-secret']);
    Http::fake([
        '*/query_range*' => Http::response(['status' => 'success', 'data' => ['resultType' => 'streams', 'result' => [
            ['stream' => ['project' => 'example', 'env' => 'production', 'level' => 'error'], 'values' => [[now()->format('U').'000000000', 'Failure']]],
        ]]]),
        '*/labels*' => Http::response(['status' => 'success', 'data' => ['project', 'env']]),
        '*/label/project/values*' => Http::response(['status' => 'success', 'data' => ['example']]),
    ]);
    $this->getJson('/api/v1/logs?project=example&search=Failure')->assertOk()->assertJsonPath('data.0.message', 'Failure')
        ->assertJsonPath('meta.summary.errors', 1)->assertDontSee('loki-secret');
    $this->getJson('/api/v1/logs/labels')->assertOk()->assertJsonPath('data.0', 'env');
    $this->getJson('/api/v1/logs/labels/project/values')->assertOk()->assertJsonPath('data.0', 'example');
    Http::assertSent(fn ($request) => str_contains($request->url(), 'query_range') && $request['query'] === '{project=~".+",project="example"} |~ "(?i)Failure"');
});

it('rejects invalid log ranges and label injection and returns explicit Loki failures', function (): void {
    config(['loki.url' => 'https://loki.example.test']);
    $this->getJson('/api/v1/logs?period=custom&start=2026-01-01&end=2026-02-01')->assertUnprocessable();
    $this->getJson('/api/v1/logs?limit=9000')->assertUnprocessable();
    $this->getJson('/api/v1/logs?labels[0][name]=bad%7D&labels[0][value]=x')->assertUnprocessable();
    Http::assertNothingSent();
    Http::fake(['*' => Http::response('loki-secret', 403)]);
    $this->getJson('/api/v1/logs')->assertStatus(502)->assertDontSee('loki-secret');
    infraAuthenticate($this, $this->user->createToken('no logs', ['infra:read'])->plainTextToken);
    $this->getJson('/api/v1/logs')->assertForbidden();
});

it('serves an OpenAPI contract matching every management operation', function (): void {
    $spec = $this->getJson('/api/v1/openapi.json')->assertOk()->assertJsonPath('openapi', '3.1.0')->json();
    foreach (Operations::all() as $name => $operation) {
        expect($spec['paths'][$operation['path']][strtolower($operation['method'])]['operationId'])->toBe($name);
        $path = preg_replace('/\{[^}]+\}/', '1', $operation['path']);
        $route = Route::getRoutes()->match(Illuminate\Http\Request::create('/api/v1'.$path, $operation['method']));
        expect($route->getActionName())->toStartWith('App\\Http\\Controllers\\Api\\');
    }
});

it('checks actual maintenance before detaching a file a project or an instance despite stale cached state', function (): void {
    $this->project->update(['maintenance_yml_file' => 'site.yml', 'maintenance_enabled' => false]);
    Http::fake(['*' => Http::response(json_encode('{{ $maintenance_enabled := 1 }}'))]);
    $this->patchJson('/api/v1/projects/'.$this->project->id.'/settings', ['maintenance_yml_file' => null])->assertConflict();
    $this->patchJson('/api/v1/projects/'.$this->project->id, ['server_id' => 'other-server'])->assertConflict();
    $this->patchJson('/api/v1/connections/'.$this->connection->id, ['base_url' => 'https://other.example.test'])->assertConflict();
    $this->deleteJson('/api/v1/projects/'.$this->project->id)->assertConflict();
    expect($this->project->refresh()->maintenance_yml_file)->toBe('site.yml')->and($this->project->server_id)->toBeNull()
        ->and($this->connection->refresh()->base_url)->toBe('https://dokploy.example.test');
});

it('also bounds delegated tokens by the global Sanctum lifetime', function (): void {
    config(['sanctum.expiration' => 60]);
    $caller = $this->user->createToken('delegator', ['infra:read', 'tokens:manage']);
    $caller->accessToken->forceFill(['created_at' => now()->subMinutes(30)])->save();
    infraAuthenticate($this, $caller->plainTextToken);
    $this->postJson('/api/v1/tokens', ['name' => 'Too long', 'expires_at' => now()->addMinutes(45)->toIso8601String()])->assertUnprocessable();
    $created = $this->postJson('/api/v1/tokens', ['name' => 'Bounded by global lifetime'])->assertCreated();
    expect(Carbon\CarbonImmutable::parse($created->json('data.expires_at'))->timestamp)->toBe($caller->accessToken->created_at->addHour()->timestamp);
});

it('lists only configured repositories branches and bounds dependency reports to their project', function (): void {
    Http::fake(['https://api.github.com/*' => Http::response([['name' => 'main'], ['name' => 'feature/test']])]);
    $this->getJson('/api/v1/projects/'.$this->project->id.'/branches')->assertOk();
    Http::assertSent(fn ($request) => str_contains($request->url(), '/repos/backtik/example/branches'));
    $this->getJson('/api/v1/projects/'.$this->project->id.'/branches?repository=another/private')->assertUnprocessable();
    $report = $this->project->dependencyReports()->create([
        'repository' => 'backtik/example', 'branch' => 'main', 'commit_sha' => str_repeat('a', 40),
        'image' => 'ghcr.io/backtik/example', 'digest' => 'sha256:'.str_repeat('a', 64),
        'platform' => 'linux/amd64', 'language_version' => 'PHP 8.4', 'built_at' => now(),
    ]);
    $package = $report->dependencies()->create(['name' => 'example/package', 'manager' => 'composer', 'installed' => '1.0.0']);
    $report->dependencies()->create(['name' => 'example/second', 'manager' => 'composer', 'installed' => '2.0.0']);
    $audit = $report->audits()->create(['manager' => 'composer', 'attempted_at' => now(), 'audited_at' => now()]);
    $audit->findings()->create(['dependency_report_package_id' => $package->id, 'severity' => 'high', 'message' => 'Example vulnerability', 'url' => 'https://example.test/advisory']);
    $this->getJson('/api/v1/projects/'.$this->project->id.'/dependencies')->assertOk()->assertJsonPath('meta.total', 1);
    $details = $this->getJson('/api/v1/projects/'.$this->project->id.'/dependencies/'.$report->id.'?per_page=1')->assertOk()
        ->assertJsonPath('data.dependencies.0.name', 'example/package')->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.audits.0.manager', 'composer')->assertJsonPath('data.dependencies.0.audit_findings.0.severity', 'high');
    $this->getJson($details->json('links.next'))->assertOk()->assertJsonPath('data.dependencies.0.name', 'example/second')
        ->assertJsonPath('meta.current_page', 2);
    $other = Project::create(['app_name' => 'Other', 'dokploy_id' => $this->connection->id]);
    $this->getJson('/api/v1/projects/'.$other->id.'/dependencies/'.$report->id)->assertNotFound();
});

it('creates bootstrap tokens through the CLI with explicit permissions and expiration', function (): void {
    $this->artisan('infra:token', ['user' => $this->user->email, '--ability' => ['infra:read', 'logs:read'], '--days' => '7'])->assertSuccessful();
    $token = $this->user->tokens()->latest('id')->first();
    expect($token->abilities)->toBe(['infra:read', 'logs:read'])->and($token->expires_at)->not->toBeNull();
    $this->artisan('infra:token', ['user' => $this->user->email, '--ability' => ['unknown']])->assertFailed();
});
