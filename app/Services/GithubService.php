<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GithubService
{
    private const BRANCH_CACHE_TTL_SECONDS = 300;

    private const GHCR_MANIFEST_ACCEPT = 'application/vnd.docker.distribution.manifest.v2+json, application/vnd.oci.image.manifest.v1+json, application/vnd.docker.distribution.manifest.list.v2+json, application/vnd.oci.image.index.v1+json';

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

    public function missingGhcrImages(Project $project, string $branch, array $selectedBranches = []): array
    {
        return collect($this->ghcrImageChecks($project, $branch, $selectedBranches))
            ->reject(fn (array $image): bool => $image['exists'])
            ->map(fn (array $image): string => $image['image'])
            ->values()
            ->all();
    }

    private function ghcrImageChecks(Project $project, string $branch, array $selectedBranches): array
    {
        $images = collect([
            [
                'owner' => $project->github_owner,
                'repository' => $project->github_repository,
                'branch' => $branch,
            ],
        ]);

        foreach ($project->linked_repositories ?? [] as $repo) {
            $placeholder = $repo['branch_placeholder'] ?? null;
            $repoBranch = $placeholder ? ($selectedBranches[$placeholder] ?? null) : null;

            if (! $repoBranch) {
                continue;
            }

            $images->push([
                'owner' => $repo['owner'] ?? null,
                'repository' => $repo['repository'] ?? null,
                'branch' => $repoBranch,
            ]);
        }

        return $images
            ->filter(fn (array $image): bool => filled($image['owner'] ?? null) && filled($image['repository'] ?? null) && filled($image['branch'] ?? null))
            ->map(function (array $image) use ($project): array {
                $path = strtolower($image['owner'].'/'.$image['repository']);
                $tag = $this->imageTag((string) $image['branch']);

                return [
                    'image' => "ghcr.io/{$path}:{$tag}",
                    'exists' => $this->ghcrManifestExists($project, $path, $tag),
                ];
            })
            ->values()
            ->all();
    }

    private function ghcrManifestExists(Project $project, string $path, string $tag): bool
    {
        try {
            $response = $this->ghcrManifestRequest()
                ->head("https://ghcr.io/v2/{$path}/manifests/{$tag}");

            if ($response->status() === 401 && $registryToken = $this->ghcrRegistryToken($project, (string) $response->header('Www-Authenticate'))) {
                $response = $this->ghcrManifestRequest()
                    ->withToken($registryToken)
                    ->head("https://ghcr.io/v2/{$path}/manifests/{$tag}");
            }

            if ($response->successful()) {
                return true;
            }

            Log::info('GHCR image manifest not available yet', [
                'image' => "ghcr.io/{$path}:{$tag}",
                'status' => $response->status(),
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::warning('GHCR image manifest check failed', [
                'image' => "ghcr.io/{$path}:{$tag}",
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function ghcrManifestRequest(): PendingRequest
    {
        return Http::accept(self::GHCR_MANIFEST_ACCEPT)
            ->withHeaders(['User-Agent' => 'dokploy-staging']);
    }

    private function ghcrRegistryToken(Project $project, string $challenge): ?string
    {
        $token = $project->github_token ?: config('services.github.token');

        if (! $token || ! str_starts_with($challenge, 'Bearer ')) {
            return null;
        }

        $params = $this->parseAuthenticateChallenge($challenge);
        $realm = $params['realm'] ?? null;

        if (! $realm) {
            return null;
        }

        $response = Http::withBasicAuth($project->github_owner, $token)
            ->acceptJson()
            ->withHeaders(['User-Agent' => 'dokploy-staging'])
            ->get($realm, collect($params)->except('realm')->all());

        if (! $response->successful()) {
            return null;
        }

        return $response->json('token') ?: $response->json('access_token');
    }

    private function parseAuthenticateChallenge(string $challenge): array
    {
        preg_match_all('/(\w+)="([^"]+)"/', $challenge, $matches, PREG_SET_ORDER);

        return collect($matches)
            ->mapWithKeys(fn (array $match): array => [$match[1] => $match[2]])
            ->all();
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

    private function imageTag(string $branch): string
    {
        return str($branch)
            ->replace(['/', '.', '_', ' '], '-')
            ->trim('-')
            ->lower()
            ->value();
    }
}
