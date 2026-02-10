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
            'pr_number' => 'required|int',
            'branch' => 'required|string|max:255',
        ]);

        app(DeployService::class)->deploy(\App\Models\Project::find($validated['project_id']), 'create', $validated['pr_number'], $validated['branch']);
    }

    public function delete(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|int|exists:projects,id',
            'pr_number' => 'required|int',
        ]);

        app(DeployService::class)->deploy(\App\Models\Project::find($validated['project_id']), 'delete', $validated['pr_number'], '');
    }
}
