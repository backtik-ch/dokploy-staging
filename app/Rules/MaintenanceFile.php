<?php

namespace App\Rules;

use App\Exceptions\MaintenanceException;
use App\Services\MaintenanceService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class MaintenanceFile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            app(MaintenanceService::class)->resolvePath($value);
        } catch (MaintenanceException $exception) {
            $fail($exception->getMessage());
        }
    }
}
