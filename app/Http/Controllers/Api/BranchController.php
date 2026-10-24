<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListBranchesRequest;
use App\Http\Resources\BranchCollection;
use App\Services\Infrastructure\BranchService;

class BranchController extends Controller
{
    public function __construct(private readonly BranchService $branches) {}

    public function index(ListBranchesRequest $request): BranchCollection
    {
        $branches = $this->branches->listBranches(
            $request->validated('project_id'),
            $request->validated('repository'),
            $request->user(),
        );

        return new BranchCollection($branches);
    }
}
