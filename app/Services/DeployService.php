<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Staging;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeployService
{
    public function deploy(Project $project, string $action, int $prNumber, string $branch, array $selectedBranches = []): ?Staging
    {
        try {
            $snakeBranch = $this->snake($branch);

            $stagingName = "staging-pr-{$prNumber}-$snakeBranch";

            foreach ($selectedBranches as $repoBranch) {
                if (! filled($repoBranch)) {
                    continue;
                }

                $stagingName .= '-'.$this->snake((string) $repoBranch);
            }

            $staging = Staging::where([
                'project_id' => $project->id,
                'pr_number' => $prNumber,
            ])->first();

            if ($action === 'create') {

                if ($staging) {
                    $this->updateCompose($project, $staging->compose_id, $branch);
                    $env = $this->injectEnvVars($project, $staging->compose_id, $prNumber, $branch, $selectedBranches ?: ($staging->selected_branches ?? []));
                    $this->deployCompose($project, $staging->compose_id);

                    $staging->update([
                        'branch' => $branch,
                        'environment' => $env,
                        'selected_branches' => $selectedBranches ?: ($staging->selected_branches ?? []),
                    ]);

                    return $staging->fresh();
                }

                $envId = $this->createEnvironment($project, $stagingName);
                Log::info("Environment ID: $envId");
                $composeId = $this->createCompose($project, $envId, $prNumber);
                Log::info("Compose ID: $composeId");
                $this->updateCompose($project, $composeId, $branch);
                $env = $this->injectEnvVars($project, $composeId, $prNumber, $branch, $selectedBranches);
                try {
                    $this->loadServices($project, $composeId);
                } catch (\Throwable $e) {
                    Log::warning('compose.loadServices failed, continuing deploy', [
                        'compose_id' => $composeId,
                        'message' => $e->getMessage(),
                    ]);

                    dd([
                        'compose_id' => $composeId,
                        'message' => $e->getMessage(),
                    ]);
                }
                $this->createDomain($project, $composeId, $stagingName);
                $this->deployCompose($project, $composeId);

                return Staging::create([
                    'project_id' => $project->id,
                    'pr_number' => $prNumber,
                    'branch' => $branch,
                    'selected_branches' => $selectedBranches,
                    'compose_id' => $composeId,
                    'environment_id' => $envId,
                    'environment' => $env,
                ]);
            }


            if ($action === 'delete') {
                if (! $staging) {
                    return null;
                }

                $this->deleteCompose($project, $staging->compose_id);
                $this->deleteEnvironment($project, $staging->environment_id);

                $staging->delete();

                return null;
            }

        } catch (\Throwable $e) {
            Log::error($e->getMessage());

            throw $e;
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
                    'description' => '',
                ],
            ],
        ];

        $response = $this->post($project, 'environment.create', $payload);

        $envId = $response['0']['result']['data']['json']['environmentId'] ?? null;

        if (! $envId) {
            throw new \Exception('Failed to create environment: '.json_encode($response));
        }

        return $envId;
    }

    protected function createCompose(Project $project, string $envId, $prNumber): ?string
    {
        $response = $this->post($project, 'compose.create', [
            '0' => [
                'json' => [
                    'name' => 'staging-pr-'.$prNumber,
                    'description' => '',
                    'environmentId' => $envId,
                    'composeType' => 'docker-compose',
                    'appName' => $project->app_name.'-pr-'.$prNumber,
                    'serverId' => $project->server_id,
                ],
            ],
        ]);

        return $response['0']['result']['data']['json']['composeId'] ?? null;
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
                    'autoDeploy' => false,
                ],
            ],
        ]);

        $input = '{"0":{"json":{"composeId":"'.$composeId.'"}}}';
        $res = $this->get($project, '/compose.getDefaultCommand?batch=1&input='.urlencode($input));

        $command = str($res->json('0.result.data.json'))->replaceFirst('docker ', '')->value();
        $command .= ' --pull always --force-recreate';

        $this->post($project, 'compose.update', [
            '0' => [
                'json' => [
                    'composeId' => $composeId,
                    'command' => $command,
                ],
            ],
        ]);

    }

    protected function injectEnvVars(Project $project, string $composeId, int $prNumber, string $branch, array $selectedBranches = []): string
    {
        $env = $project->environment_staging;

        $env = str($env)->replace('{PR_NUMBER}', $prNumber);
        $env = str($env)->replace('{BRANCH}', $this->snake($branch));

        foreach ($selectedBranches as $placeholder => $repoBranch) {
            $env = $env->replace('{'.strtoupper((string) $placeholder).'}', $this->snake((string) $repoBranch));
        }

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
            'serviceName' => $project->service_name ?? 'server',
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

        if ($response->failed()) {
            throw new \Exception('Request failed: '.$response->body());
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

        if ($response->failed()) {
            throw new \Exception('Request failed: '.$response->body());
        }

        return $response;
    }

    protected function loadServices(Project $project, string $composeId): void
    {
        $input = json_encode([
            '0' => [
                'json' => [
                    'composeId' => $composeId,
                    'type' => 'fetch',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->get($project, '/compose.loadServices?batch=1&input='.urlencode($input));
    }

    private function snake(string $branch): string
    {
        return str($branch)
            ->replace(['/', '.', '_'], '-')
            ->trim('-')
            ->lower()
            ->value();
    }
}
