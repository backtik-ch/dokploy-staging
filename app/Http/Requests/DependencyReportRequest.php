<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

class DependencyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Invalid identifiers are handled by validation rather than treated as authorization failures.
        if (filter_var($this->input('project_id'), FILTER_VALIDATE_INT) === false) {
            return true;
        }

        return $this->user()->can('update', $this->project());
    }

    public function project(): Project
    {
        return Project::findOrFail($this->integer('project_id'));
    }

    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', 'min:1'],
            'image' => ['required', 'string', 'max:255', 'regex:~^ghcr\.io/[a-z0-9_.-]+/[a-z0-9_./-]+$~'],
            'digest' => ['required', 'regex:/^sha256:[a-f0-9]{64}$/'],
            'platform' => ['required', 'string', 'max:100', 'regex:~^linux/[a-z0-9_]+(?:/[a-zA-Z0-9_]+)?$~'],
        ];
    }
}
