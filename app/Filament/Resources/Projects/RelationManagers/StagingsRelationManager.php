<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Filament\Resources\Projects\Resources\Stagings\StagingResource;
use Filament\Resources\RelationManagers\RelationManager;

class StagingsRelationManager extends RelationManager
{
    protected static string $relationship = 'stagings';

    protected static ?string $relatedResource = StagingResource::class;
}
