<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GithubService
{
    public function listBranches(Project $project, string $owner, string $repository): array
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
}
