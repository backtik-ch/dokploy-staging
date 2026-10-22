<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\Pages\Concerns\HasProjectHeader;
use App\Filament\Resources\Projects\ProjectResource;
use Filament\Resources\Pages\ViewRecord;

class ViewProject extends ViewRecord
{
    use HasProjectHeader;

    protected static string $resource = ProjectResource::class;

    protected static ?string $navigationLabel = 'Vue d’ensemble';
}
