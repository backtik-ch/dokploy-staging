<?php

namespace App\Services\Infrastructure;

use App\Models\User;
use Illuminate\Support\Arr;

class CapabilityService
{
    public function forUser(User $user): array
    {
        ApiAccess::authorize($user, 'infra:read');

        return [
            'version' => '1.0.0',
            'abilities' => ApiAccess::ABILITIES,
            'operations' => collect(Operations::all())
                ->filter(fn (array $operation): bool => ApiAccess::allows($user, $operation['ability']))
                ->map(fn (array $operation): array => Arr::only($operation, ['name', 'method', 'path', 'ability', 'description']))
                ->values()->all(),
            'logs_configured' => filled(config('loki.url')),
        ];
    }
}
