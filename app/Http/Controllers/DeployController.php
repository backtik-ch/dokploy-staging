<?php

namespace App\Http\Controllers;

use App\Services\DeployService;
use Illuminate\Http\Request;

class DeployController extends Controller
{
    public function deploy(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|int|exists:projects,id',
            'staging_reference' => 'nullable|string|max:255|required_without:pr_number',
            'pr_number' => 'nullable|string|max:255|required_without:staging_reference',
            'branch' => 'required|string|max:255',
            'selected_branches' => 'nullable|array',
            'selected_branches.*' => 'nullable|string|max:255',
        ]);

        $reference = (string) ($validated['staging_reference'] ?? $validated['pr_number']);

        app(DeployService::class)->deploy(
            \App\Models\Project::find($validated['project_id']),
            'create',
            $reference,
            $validated['branch'],
            $validated['selected_branches'] ?? [],
            true,
        );
    }

    public function delete(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|int|exists:projects,id',
            'staging_reference' => 'nullable|string|max:255|required_without:pr_number',
            'pr_number' => 'nullable|string|max:255|required_without:staging_reference',
        ]);

        $reference = (string) ($validated['staging_reference'] ?? $validated['pr_number']);

        app(DeployService::class)->deploy(\App\Models\Project::find($validated['project_id']), 'delete', $reference, '');
    }
}
