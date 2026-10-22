<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\Pages\Concerns\HasProjectHeader;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Projects\Resources\Stagings\StagingResource;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables\Table;

class ManageProjectStagings extends ManageRelatedRecords
{
    use HasProjectHeader;

    protected static string $resource = ProjectResource::class;

    protected static string $relationship = 'stagings';

    protected static ?string $relatedResource = StagingResource::class;

    protected static ?string $navigationLabel = 'Stagings';

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->heading('Stagings de '.$this->getRecord()->app_name)
            ->description('Environnements de prévisualisation du projet.');
    }
}
