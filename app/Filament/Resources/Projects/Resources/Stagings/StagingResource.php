<?php

namespace App\Filament\Resources\Projects\Resources\Stagings;

use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Projects\Resources\Stagings\Pages\CreateStaging;
use App\Filament\Resources\Projects\Resources\Stagings\Pages\EditStaging;
use App\Filament\Resources\Projects\Resources\Stagings\Schemas\StagingForm;
use App\Filament\Resources\Projects\Resources\Stagings\Tables\StagingsTable;
use App\Models\Staging;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class StagingResource extends Resource
{
    protected static ?string $model = Staging::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $parentResource = ProjectResource::class;

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return StagingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StagingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'create' => CreateStaging::route('/create'),
            'edit' => EditStaging::route('/{record}/edit'),
        ];
    }
}
