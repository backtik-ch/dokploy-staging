<?php

use App\Models\Dokploy;
use App\Models\Project;
use App\Models\User;
use App\Services\GithubService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('loads services with a batch object and queues API deployments until images exist', function (): void {
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    Sanctum::actingAs(User::factory()->create());

    $dokploy = Dokploy::create([
        'base_url' => 'https://dokploy.example.test',
        'token' => 'test-token',
    ]);

    $project = Project::create([
        'dokploy_id' => $dokploy->id,
        'app_name' => 'example',
        'dokploy_project_id' => 'project-id',
        'github_id' => 'github-id',
        'github_owner' => 'example',
        'github_repository' => 'infra',
        'compose_name_file' => 'compose.yml',
        'domain_name' => 'example.test',
        'extra_sub_domains' => [],
        'environment_staging' => 'APP_BRANCH={APP_BRANCH}',
    ]);

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

    $this->postJson('/api/deploy', [
        'project_id' => $project->id,
        'staging_reference' => 'feat-staging-test',
        'branch' => 'main',
        'selected_branches' => ['APP_BRANCH' => 'feat/staging-test'],
    ])->assertSuccessful();

    Http::assertSent(function (Request $request): bool {
        if (! str_contains($request->url(), 'compose.loadServices')) {
            return false;
        }

        parse_str(parse_url($request->url(), PHP_URL_QUERY), $query);
        $input = json_decode($query['input'], flags: JSON_THROW_ON_ERROR);

        return $request->method() === 'GET'
            && $query['batch'] === '1'
            && $input instanceof stdClass
            && $input->{'0'}->json->composeId === 'compose-id'
            && $input->{'0'}->json->type === 'fetch';
    });

    $staging = $project->stagings()->sole();
    expect($staging->deploy_status)->toBe('waiting_for_images')
        ->and($staging->selected_branches)->toBe(['APP_BRANCH' => 'feat/staging-test'])
        ->and($staging->environment)->toBe('APP_BRANCH=feat-staging-test');

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'compose.deploy'));

    $this->mock(GithubService::class, function ($mock) use ($project): void {
        $mock->shouldReceive('missingGhcrImages')
            ->twice()
            ->withArgs(fn (Project $actualProject, string $branch, array $selectedBranches): bool => $actualProject->is($project)
                && $branch === 'main'
                && $selectedBranches === ['APP_BRANCH' => 'feat/staging-test'])
            ->andReturn(['ghcr.io/example/app:feat-staging-test'], []);
    });

    $this->artisan('staging:deploy-ready')->assertSuccessful();
    expect($staging->fresh()->deploy_status)->toBe('waiting_for_images')
        ->and($staging->fresh()->missing_images)->toBe(['ghcr.io/example/app:feat-staging-test']);
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'compose.deploy'));

    $this->artisan('staging:deploy-ready')->assertSuccessful();
    expect($staging->fresh()->deploy_status)->toBe('deployed');
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'compose.deploy')
        && $request->data()['0']['json']['composeId'] === 'compose-id');
});
