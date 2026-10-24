<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Api\ListBranchesRequest;
use App\Http\Resources\BranchCollection;
use App\Mcp\InfrastructureTool;
use App\Models\User;
use App\Services\Infrastructure\BranchService;

class ListBranchesTool extends InfrastructureTool
{
    protected const REQUEST = ListBranchesRequest::class;

    public function __construct(private readonly BranchService $branches) {}

    protected function resource(array $data, User $user): BranchCollection
    {
        return new BranchCollection($this->branches->listBranches($data['project_id'], $data['repository'] ?? null, $user));
    }
}
