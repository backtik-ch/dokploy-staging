<?php

namespace App\Http\Requests\Api;

use App\Models\User;
use App\Services\Infrastructure\ApiAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator as ValidatorFactory;
use Illuminate\Validation\Validator;

abstract class InfrastructureRequest extends FormRequest
{
    public const OPERATION = '';

    public const ABILITY = '';

    protected const ID_RULES = ['required', 'integer', 'min:1'];

    public function authorize(): bool
    {
        ApiAccess::authorize($this->user(), static::ABILITY);

        return true;
    }

    public function validationData(): array
    {
        // Validate the path identifiers too; body/query data cannot override them.
        return [...$this->all(), ...($this->route()?->parametersWithoutNulls() ?? [])];
    }

    public function after(): array
    {
        return [fn (Validator $validator) => $this->rejectUnknownFields($validator, $this->validationData())];
    }

    /** MCP validates its arguments against the same rules without running the HTTP lifecycle. */
    public static function validateInput(array $input, User $user): array
    {
        ApiAccess::authorize($user, static::ABILITY);
        $request = new static;

        return ValidatorFactory::make($input, $request->rules())
            ->after(fn (Validator $validator) => $request->rejectUnknownFields($validator, $input))
            ->validate();
    }

    protected function paginationRules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    private function rejectUnknownFields(Validator $validator, array $input): void
    {
        $allowed = array_unique(array_map(fn (string $key): string => explode('.', $key)[0], array_keys($this->rules())));
        foreach (array_diff(array_keys($input), $allowed) as $field) {
            $validator->errors()->add($field, 'Champ inconnu pour cette opération.');
        }
    }
}
