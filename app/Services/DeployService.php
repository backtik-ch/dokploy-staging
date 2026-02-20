<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Staging;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class DeployService
{
    public function deploy(Project $project, string $action, int $prNumber, string $branch): ?Staging
    {
        $stagingName = "staging-pr-{$prNumber}";

        $staging = Staging::where([
            'project_id' => $project->id,
            'pr_number' => $prNumber,
        ])->first();

        if ($action === 'create') {

            if ($staging) {
                $this->deployCompose($project, $staging->compose_id);

                return $staging;
            }

            // $this->info("Creating staging for PR #{$prNumber}");

            $envId = $this->createEnvironment($project, $stagingName);
            $composeId = $this->createCompose($project, $envId, $prNumber);
            $this->updateCompose($project, $composeId, $branch);
            $env = $this->injectEnvVars($project, $composeId, $prNumber);
            $this->loadServices($project, $composeId);
            $this->createDomain($project, $composeId, $stagingName);
            $this->deployCompose($project, $composeId);

            return Staging::create([
                'project_id' => $project->id,
                'pr_number' => $prNumber,
                'branch' => $branch,
                'compose_id' => $composeId,
                'environment_id' => $envId,
                'environment' => $env,
            ]);
        }

        if ($action === 'delete') {
            $this->deleteCompose($project, $staging->compose_id);
            $this->deleteEnvironment($project, $staging->environment_id);

            $staging->delete();

            return null;
        }

        return null;
    }

    protected function createEnvironment(Project $project, string $stagingName): string
    {
        $payload = [
            '0' => [
                'json' => [
                    'projectId' => $project->dokploy_project_id,
                    'name' => $stagingName,
                    'description' => null,
                ],
            ],
        ];

        $response = $this->post($project, 'environment.create', $payload);

        $envId = $response['0']['result']['data']['json']['environmentId'] ?? null;

        if (! $envId) {
            dd('Failed to create environment: '.json_encode($response));
        }

        // $this->info("✅ Environment ID: $envId");

        return $envId;
    }

    protected function createCompose(Project $project, string $envId, $prNumber): string
    {
        $response = $this->post($project, 'compose.create', [
            '0' => [
                'json' => [
                    'name' => 'app',
                    'description' => '',
                    'environmentId' => $envId,
                    'composeType' => 'docker-compose',
                    'appName' => $project->app_name . '-pr-'.$prNumber,
                    'serverId' => $project->server_id,
                ],
            ],
        ]);

        $composeId = $response['0']['result']['data']['json']['composeId'] ?? null;
        // $this->info("✅ Compose ID: $composeId");

        return $composeId;
    }

    protected function updateCompose(Project $project, string $composeId, string $branch): void
    {
        $this->post($project, 'compose.update', [
            '0' => [
                'json' => [
                    'branch' => $branch,
                    'repository' => $project->github_repository,
                    'composeId' => $composeId,
                    'owner' => $project->github_owner,
                    'composePath' => $project->compose_name_file,
                    'githubId' => $project->github_id,
                    'serverId' => $project->server_id,
                    'sourceType' => 'github',
                    'composeStatus' => 'idle',
                    'watchPaths' => [],
                    'enableSubmodules' => false,
                    'triggerType' => 'push',
                ],
            ],
        ]);
    }

    protected function injectEnvVars(Project $project, string $composeId, int $prNumber): string
    {
        $env = $project->environment_staging;

        $env = str($env)->replace('${PR_NUMBER}', $prNumber);

        $this->post($project, 'compose.update', [
            '0' => [
                'json' => [
                    'composeId' => $composeId,
                    'env' => $env->value(),
                ],
            ],
        ]);

        return $env;
    }

    protected function deployCompose(Project $project, string $composeId): void
    {
        $this->post($project, 'compose.deploy', [
            '0' => ['json' => ['composeId' => $composeId]],
        ]);
    }

    protected function createDomain(Project $project, string $composeId, string $stagingName): void
    {
        $basePayload = [
            'domainId' => '',
            'composeId' => $composeId,
            'port' => 80,
            'https' => true,
            'certificateType' => 'letsencrypt',
            'serviceName' => 'server',
            'domainType' => 'compose',
        ];

        $this->post($project, 'domain.create', [
            '0' => [
                'json' => $basePayload + [
                    'host' => "{$stagingName}.{$project->domain_name}",
                ],
            ],
        ]);

        foreach ($project->extra_sub_domains as $extra) {
            $this->post($project, 'domain.create', [
                '0' => [
                    'json' => $basePayload + [
                        'host' => "{$extra}.{$stagingName}.{$project->domain_name}",
                    ],
                ],
            ]);
        }
    }

    protected function deleteEnvironment(Project $project, string $envId): void
    {
        $this->post($project, 'environment.remove', [
            '0' => [
                'json' => [
                    'environmentId' => $envId,
                ],
            ],
        ]);
    }

    protected function deleteCompose(Project $project, string $composeId): void
    {
        $this->post($project, 'compose.delete', [
            '0' => [
                'json' => [
                    'mongoId' => $composeId,
                    'postgresId' => $composeId,
                    'redisId' => $composeId,
                    'mysqlId' => $composeId,
                    'mariadbId' => $composeId,
                    'applicationId' => $composeId,
                    'composeId' => $composeId,
                    'deleteVolumes' => true,
                ],
            ],
        ]);
    }

    protected function post(Project $project, string $endpoint, array $payload): array
    {
        $response = Http::withHeaders([
            'x-api-key' => $project->dokploy->token,
            'Content-Type' => 'application/json',
        ])->baseUrl($project->dokploy->base_url)
            ->withBody(json_encode((object) $payload))
            ->post("/api/trpc/$endpoint?batch=1");

        if (! $response->successful()) {
            dd("❌ Error calling POST {$endpoint}: ".$response->body());
        }

        return $response->json();
    }

    protected function get(Project $project, string $endpoint): Response
    {
        $response = Http::withHeaders([
            'x-api-key' => $project->dokploy->token,
            'Content-Type' => 'application/json',
        ])->baseUrl($project->dokploy->base_url)
            ->get("/api/trpc/$endpoint");

        if (! $response->successful()) {
            dd("❌ Error calling GET {$endpoint}: ".$response->body());
        }

        return $response;
    }

    private function getEnvironment(Project $project, string $stagingName): array
    {
        $res = $this->get($project, '/project.one?projectId='.$project->dokploy_project_id);

        return collect($res->json('environments') ?? [])
            ->where('name', $stagingName)
            ->first() ?? [];
    }

    private function loadServices(Project $project, string $composeId)
    {
        $data = '{"0":{"json":{"composeId":"'.$composeId.'","type":"fetch"}}}';
        $this->get($project, '/compose.loadServices?batch=1&input='.urlencode($data));
    }
}
