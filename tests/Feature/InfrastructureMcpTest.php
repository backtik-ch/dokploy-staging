<?php

use App\Models\Dokploy;
use App\Models\Project;
use App\Models\User;
use App\Services\Infrastructure\Operations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    $this->user = User::factory()->create();
    $this->withToken($this->user->createToken('mcp', ['*'])->plainTextToken);
    $this->connection = Dokploy::create(['name' => 'Production', 'base_url' => 'https://dokploy.example.test', 'token' => 'private-dokploy']);
    $this->project = Project::create(['app_name' => 'Example', 'dokploy_id' => $this->connection->id, 'github_token' => 'private-github']);
});

function infrastructureRpc($test, string $method, array $params = [])
{
    return $test->postJson('/api/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => (object) $params], ['MCP-Protocol-Version' => '2025-06-18']);
}

it('supports MCP initialization notifications and tool discovery over HTTP', function (): void {
    infrastructureRpc($this, 'initialize', ['protocolVersion' => '2025-06-18', 'capabilities' => (object) [], 'clientInfo' => ['name' => 'test', 'version' => '1']])
        ->assertOk()->assertJsonPath('result.protocolVersion', '2025-06-18')->assertJsonPath('result.serverInfo.name', 'Backtik Infrastructure');
    $this->postJson('/api/mcp', ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'])->assertAccepted();
    $response = infrastructureRpc($this, 'tools/list')->assertOk();
    expect($response->json('result.tools'))->toHaveCount(count(Operations::all()));
    $tools = collect($response->json('result.tools'))->keyBy('name');
    expect($tools['projects_get']['annotations']['readOnlyHint'])->toBeTrue()
        ->and($tools['stagings_delete']['annotations']['destructiveHint'])->toBeTrue()
        ->and($tools['stagings_create']['inputSchema']['required'])->toContain('project_id', 'staging_reference', 'branch');
});

it('supports the current MCP protocol discovery headers', function (): void {
    $this->postJson('/api/mcp', [
        'jsonrpc' => '2.0', 'id' => 1, 'method' => 'server/discover',
        'params' => ['_meta' => [
            'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
            'io.modelcontextprotocol/clientCapabilities' => (object) [],
        ]],
    ], ['MCP-Protocol-Version' => '2026-07-28', 'Mcp-Method' => 'server/discover'])
        ->assertOk()->assertJsonPath('result.supportedVersions.0', '2026-07-28');
});

it('executes management tools using the same validation and response contract as REST', function (): void {
    infrastructureRpc($this, 'tools/call', ['name' => 'projects_get', 'arguments' => ['id' => $this->project->id]])
        ->assertOk()->assertJsonPath('result.isError', false)->assertJsonPath('result.structuredContent.data.app_name', 'Example')
        ->assertDontSee('private-github')->assertJsonMissingPath('result.structuredContent.data.github_token');
    infrastructureRpc($this, 'tools/call', ['name' => 'projects_update', 'arguments' => ['id' => $this->project->id, 'app_name' => 'Changed by MCP']])
        ->assertOk()->assertJsonPath('result.isError', false);
    $this->getJson('/api/v1/projects/'.$this->project->id)->assertOk()->assertJsonPath('data.app_name', 'Changed by MCP');
    infrastructureRpc($this, 'tools/call', ['name' => 'projects_update', 'arguments' => ['id' => $this->project->id, 'unknown' => true]])
        ->assertOk()->assertJsonPath('result.isError', true)->assertSee('422');
    infrastructureRpc($this, 'tools/call', ['name' => 'projects_get', 'arguments' => ['id' => 99999]])
        ->assertOk()->assertJsonPath('result.isError', true)->assertSee('404');
});

it('exposes only tools granted by the bearer token and prevents hidden calls', function (): void {
    app('auth')->forgetGuards();
    $this->withToken($this->user->createToken('reader', ['infra:read'])->plainTextToken);
    $names = collect(infrastructureRpc($this, 'tools/list')->assertOk()->json('result.tools'))->pluck('name');
    expect($names)->toContain('projects_list')->not->toContain('projects_delete', 'logs_search', 'tokens_create', 'maintenance_set');
    infrastructureRpc($this, 'tools/call', ['name' => 'projects_delete', 'arguments' => ['id' => $this->project->id]])
        ->assertStatus(400)->assertJsonPath('error.code', -32602);
    expect($this->project->refresh()->deleted_at)->toBeNull();
});

it('preserves secondary secret permissions when a mutation tool is available', function (): void {
    app('auth')->forgetGuards();
    $this->withToken($this->user->createToken('writer', ['infra:write'])->plainTextToken);
    infrastructureRpc($this, 'tools/call', ['name' => 'projects_update', 'arguments' => ['id' => $this->project->id, 'github_token' => 'override']])
        ->assertOk()->assertJsonPath('result.isError', true)->assertSee('403');
    expect($this->project->refresh()->github_token)->toBe('private-github');
});

it('serializes paginated MCP results with the same resources and links to the REST endpoint', function (): void {
    Project::create(['app_name' => 'Example second', 'dokploy_id' => $this->connection->id, 'github_token' => 'second-secret']);
    $rest = $this->getJson('/api/v1/projects?search=Example&per_page=1')->assertOk();
    $response = infrastructureRpc($this, 'tools/call', ['name' => 'projects_list', 'arguments' => ['search' => 'Example', 'per_page' => 1]])
        ->assertOk()->assertJsonPath('result.isError', false)->assertJsonPath('result.structuredContent.meta.total', 2)
        ->assertJsonMissingPath('result.structuredContent.data.0.github_token')->assertDontSee('second-secret');
    expect($response->json('result.structuredContent.data'))->toBe($rest->json('data'));
    $next = $response->json('result.structuredContent.links.next');
    expect(parse_url($next, PHP_URL_PATH))->toBe('/api/v1/projects');
    parse_str(parse_url($next, PHP_URL_QUERY), $query);
    expect($query)->toMatchArray(['search' => 'Example', 'per_page' => '1', 'page' => '2']);
    $this->getJson($next)->assertOk()->assertJsonPath('data.0.app_name', 'Example second');
});

it('provides log search through MCP with structured results and explicit remote errors', function (): void {
    config(['loki.url' => 'https://loki.example.test']);
    Http::fake(['https://loki.example.test/*' => Http::response(['status' => 'success', 'data' => ['resultType' => 'streams', 'result' => []]])]);
    infrastructureRpc($this, 'tools/call', ['name' => 'logs_search', 'arguments' => ['period' => '15m', 'project' => 'example']])
        ->assertOk()->assertJsonPath('result.isError', false)->assertJsonPath('result.structuredContent.meta.summary.count', 0);
    infrastructureRpc($this, 'tools/call', ['name' => 'logs_search', 'arguments' => ['limit' => 99999]])
        ->assertOk()->assertJsonPath('result.isError', true)->assertSee('422');
    config(['loki.url' => 'https://unreachable.example.test']);
    Http::fake(['*' => Http::response('private-loki-body', 403)]);
    infrastructureRpc($this, 'tools/call', ['name' => 'logs_search', 'arguments' => (object) []])
        ->assertOk()->assertJsonPath('result.isError', true)->assertSee('502')->assertDontSee('private-loki-body');
});

it('requires authentication and rejects cross origin browser access', function (): void {
    $this->postJson('/api/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], ['Origin' => 'https://untrusted.example.test'])->assertForbidden();
    app('auth')->forgetGuards();
    $this->withToken('invalid');
    infrastructureRpc($this, 'tools/list')->assertUnauthorized();
});

it('creates reads rotates and deletes tokens through their dedicated MCP tools', function (): void {
    $created = infrastructureRpc($this, 'tools/call', ['name' => 'tokens_create', 'arguments' => ['name' => 'MCP reader', 'abilities' => ['infra:read']]])
        ->assertOk()->assertJsonPath('result.isError', false);
    $id = $created->json('result.structuredContent.data.id');
    $secret = $created->json('result.structuredContent.data.plain_text_token');
    expect($secret)->toBeString()->not->toBeEmpty();
    infrastructureRpc($this, 'tools/call', ['name' => 'tokens_get', 'arguments' => ['id' => $id]])
        ->assertOk()->assertJsonPath('result.isError', false)
        ->assertJsonMissingPath('result.structuredContent.data.plain_text_token')->assertDontSee($secret);
    $rotated = infrastructureRpc($this, 'tools/call', ['name' => 'tokens_rotate', 'arguments' => ['id' => $id]])
        ->assertOk()->assertJsonPath('result.isError', false);
    $replacementId = $rotated->json('result.structuredContent.data.id');
    expect($replacementId)->not->toBe($id);
    infrastructureRpc($this, 'tools/call', ['name' => 'tokens_get', 'arguments' => ['id' => $id]])
        ->assertOk()->assertJsonPath('result.isError', true)->assertSee('404');
    infrastructureRpc($this, 'tools/call', ['name' => 'tokens_delete', 'arguments' => ['id' => $replacementId]])
        ->assertOk()->assertJsonPath('result.structuredContent.data.deleted', true);
    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $replacementId]);
});

it('configures reads and removes maintenance through separate MCP tools', function (): void {
    $content = '{{ $maintenance_enabled := 0 }}';
    Http::fake(function ($request) use (&$content) {
        if ($request->method() === 'POST') {
            $content = $request['traefikConfig'];

            return Http::response(['success' => true]);
        }

        return Http::response(json_encode($content));
    });
    infrastructureRpc($this, 'tools/call', ['name' => 'maintenance_set', 'arguments' => [
        'project_id' => $this->project->id, 'maintenance_yml_file' => 'site.yml', 'enabled' => true,
    ]])->assertOk()->assertJsonPath('result.isError', false)->assertJsonPath('result.structuredContent.data.maintenance_enabled', true);
    infrastructureRpc($this, 'tools/call', ['name' => 'maintenance_get', 'arguments' => ['project_id' => $this->project->id]])
        ->assertOk()->assertJsonPath('result.structuredContent.data.maintenance_enabled', true);
    infrastructureRpc($this, 'tools/call', ['name' => 'maintenance_delete', 'arguments' => ['project_id' => $this->project->id]])
        ->assertOk()->assertJsonPath('result.isError', false)->assertJsonPath('result.structuredContent.data.maintenance_yml_file', null);
    expect($content)->toContain(':= 0');
});
