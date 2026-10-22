<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDependencyReportRequest extends DependencyReportRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'repository' => ['required', 'string', 'max:255', 'regex:~^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$~'],
            'branch' => ['required', Rule::in(['main'])],
            'commit_sha' => ['required', 'regex:/^[a-f0-9]{40}$/'],
            'language_version' => ['required', 'string', 'max:255'],
            'built_at' => ['required', 'date', 'before_or_equal:now'],
            'dependencies' => ['present', 'array', 'list', 'max:5000'],
            'dependencies.*' => ['array:name,manager,path,required,locked,installed,locked_reference,installed_reference,dev,direct'],
            'dependencies.*.name' => ['required', 'string', 'max:255'],
            'dependencies.*.manager' => ['sometimes', 'string', 'max:100'],
            'dependencies.*.path' => ['sometimes', 'string', 'max:1024'],
            'dependencies.*.required' => ['sometimes', 'nullable', 'string', 'max:255'],
            'dependencies.*.locked' => ['sometimes', 'nullable', 'string', 'max:255'],
            'dependencies.*.installed' => ['sometimes', 'nullable', 'string', 'max:255'],
            'dependencies.*.locked_reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'dependencies.*.installed_reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'dependencies.*.dev' => ['sometimes', 'boolean'],
            'dependencies.*.direct' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $seen = [];
            foreach ($this->input('dependencies', []) as $index => $dependency) {
                $key = json_encode([$dependency['manager'] ?? '', $dependency['path'] ?? '', $dependency['name']]);
                if (isset($seen[$key])) {
                    $validator->errors()->add("dependencies.{$index}.name", 'Cette dépendance est déjà présente pour ce gestionnaire et ce chemin.');
                }
                $seen[$key] = true;
            }
        }];
    }
}
