<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GithubService
{
    private const BRANCH_CACHE_TTL_SECONDS = 300;

    public function listBranches(Project $project, string $owner, string $repository): array
    {
        $cacheKey = $this->cacheKey($project, $owner, $repository);

        return Cache::remember($cacheKey, self::BRANCH_CACHE_TTL_SECONDS, fn (): array => $this->fetchBranches($project, $owner, $repository));
    }

    public function warmProjectBranchesCache(Project $project): void
    {
        $repos = collect([
            [
                'owner' => $project->github_owner,
                'repository' => $project->github_repository,
            ],
            ...($project->linked_repositories ?? []),
        ])
            ->filter(fn (array $repo) => filled($repo['owner'] ?? null) && filled($repo['repository'] ?? null))
            ->unique(fn (array $repo) => ($repo['owner'] ?? '').'/'.($repo['repository'] ?? ''))
            ->values();

        foreach ($repos as $repo) {
            $this->listBranches($project, (string) $repo['owner'], (string) $repo['repository']);
        }
    }

    private function fetchBranches(Project $project, string $owner, string $repository): array
    {
        $token = $project->github_token ?: config('services.github.token');

        if (! $token || ! $owner || ! $repository) {
            return [];
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->withHeaders([
                    'X-GitHub-Api-Version' => '2022-11-28',
                    'User-Agent' => 'dokploy-staging',
                ])
                ->get("https://api.github.com/repos/{$owner}/{$repository}/branches", [
                    'per_page' => 100,
                ]);

            if (! $response->successful()) {
                Log::warning('Unable to fetch GitHub branches', [
                    'owner' => $owner,
                    'repository' => $repository,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [];
            }

            $branches = collect($response->json())
                ->pluck('name')
                ->filter()
                ->values()
                ->all();

            return array_combine($branches, $branches) ?: [];
        } catch (\Throwable $e) {
            Log::warning('GitHub branch fetch failed', [
                'owner' => $owner,
                'repository' => $repository,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function cacheKey(Project $project, string $owner, string $repository): string
    {
        return implode(':', [
            'github',
            'branches',
            $project->id,
            strtolower($owner),
            strtolower($repository),
        ]);
    }
}
