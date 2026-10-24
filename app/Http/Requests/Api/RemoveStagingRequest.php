<?php

namespace App\Http\Requests\Api;

use App\Services\Infrastructure\ApiAccess;
use Illuminate\Foundation\Http\FormRequest;

class RemoveStagingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ApiAccess::allows($this->user(), 'stagings:delete');
    }

    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'staging_reference' => ['nullable', 'string', 'max:255', 'required_without:pr_number'],
            'pr_number' => ['nullable', 'string', 'max:255', 'required_without:staging_reference'],
        ];
    }
}
