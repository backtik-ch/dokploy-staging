<?php

use App\Models\Project;
use App\Services\GithubService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('only waits for GHCR images enabled on the project', function (): void {
    $project = Project::make([
        'github_owner' => 'backtik-ch',
        'github_repository' => 'infra',
        'wait_for_main_image' => false,
        'linked_repositories' => [
            [
                'label' => 'API',
                'owner' => 'backtik-ch',
                'repository' => 'api',
                'branch_placeholder' => 'API_BRANCH',
                'wait_for_image' => false,
            ],
            [
                'label' => 'Frontend',
                'owner' => 'backtik-ch',
                'repository' => 'frontend',
                'branch_placeholder' => 'FRONTEND_BRANCH',
                'wait_for_image' => true,
            ],
            [
                'label' => 'Worker',
                'owner' => 'backtik-ch',
                'repository' => 'worker',
                'branch_placeholder' => 'WORKER_BRANCH',
            ],
        ],
    ]);

    Http::fake(function (Request $request) {
        return Http::response(
            body: '',
            status: str_contains($request->url(), '/frontend/') ? 404 : 200,
        );
    });

    $missing = app(GithubService::class)->missingGhcrImages($project, 'main', [
        'API_BRANCH' => 'feature/api',
        'FRONTEND_BRANCH' => 'feature/frontend',
        'WORKER_BRANCH' => 'feature/worker',
    ]);

    expect($missing)->toBe([
        'ghcr.io/backtik-ch/frontend:feature-frontend',
    ]);

    Http::assertSentCount(2);
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/infra/'));
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/api/'));
});
