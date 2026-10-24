<?php

namespace App\Services\Infrastructure;

use App\Models\Project;
use App\Models\User;
use App\Services\GithubService;
use Illuminate\Validation\ValidationException;

class BranchService
{
    public function __construct(
        private readonly ProjectService $projects,
        private readonly GithubService $github,
        private readonly RemoteOperation $remote,
    ) {}

    public function listBranches(int $projectId, ?string $repository, User $user): array
    {
        ApiAccess::authorize($user, 'infra:read');
        $project = $this->projects->find($projectId, $user);
        $selected = $this->configuredRepository($project, $repository);

        return $this->remote->run(fn () => $this->github->listBranches($project, $selected['owner'], $selected['repository']));
    }

    private function configuredRepository(Project $project, ?string $name): array
    {
        $repositories = collect([
            ['owner' => $project->github_owner, 'repository' => $project->github_repository],
            ...($project->linked_repositories ?? []),
        ]);
        $repository = $name === null ? $repositories->first()
            : $repositories->first(fn (array $repository): bool => $name === $repository['owner'].'/'.$repository['repository']);
        if (! $repository || blank($repository['owner']) || blank($repository['repository'])) {
            throw ValidationException::withMessages(['repository' => 'Choisissez un dépôt configuré pour ce projet (owner/repository).']);
        }

        return $repository;
    }
}
