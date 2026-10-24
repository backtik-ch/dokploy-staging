<?php

namespace App\Http\Controllers;

use App\Http\Requests\Api\DeployStagingRequest;
use App\Http\Requests\Api\RemoveStagingRequest;
use App\Services\DeployService;

class DeployController extends Controller
{
    public function deploy(DeployStagingRequest $request)
    {
        $validated = $request->validated();

        $reference = (string) ($validated['staging_reference'] ?? $validated['pr_number']);

        app(DeployService::class)->deploy(
            \App\Models\Project::find($validated['project_id']),
            'create',
            $reference,
            $validated['branch'],
            $validated['selected_branches'] ?? null,
            true,
        );
    }

    public function delete(RemoveStagingRequest $request)
    {
        $validated = $request->validated();

        $reference = (string) ($validated['staging_reference'] ?? $validated['pr_number']);

        app(DeployService::class)->deploy(\App\Models\Project::find($validated['project_id']), 'delete', $reference, '');
    }
}
