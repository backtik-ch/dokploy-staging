<?php

namespace App\Filament\Resources\Projects\Resources\Stagings\Pages;

use App\Filament\Resources\Projects\Resources\Stagings\StagingResource;
use App\Models\Staging;
use App\Services\DeployService;
use Filament\Resources\Pages\CreateRecord;

class CreateStaging extends CreateRecord
{
    protected static string $resource = StagingResource::class;

    protected function handleRecordCreation(array $data): Staging
    {
        $staging = app(DeployService::class)->deploy(
            $this->getOwnerRecord(),
            'create',
            (string) $data['staging_reference'],
            (string) $data['branch'],
            $data['selected_branches'] ?? [],
        );

        if (! $staging) {
            throw new \RuntimeException('Staging creation failed.');
        }

        return $staging;
    }
}
