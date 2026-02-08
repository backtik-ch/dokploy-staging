<?php

namespace App\Filament\Resources\Dokploys;

use App\Filament\Resources\Dokploys\Pages\CreateDokploy;
use App\Filament\Resources\Dokploys\Pages\EditDokploy;
use App\Filament\Resources\Dokploys\Pages\ListDokploys;
use App\Filament\Resources\Dokploys\Schemas\DokployForm;
use App\Filament\Resources\Dokploys\Tables\DokploysTable;
use App\Models\Dokploy;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DokployResource extends Resource
{
    protected static ?string $model = Dokploy::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return DokployForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DokploysTable::configure($table);
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
            'index' => ListDokploys::route('/'),
            'create' => CreateDokploy::route('/create'),
            'edit' => EditDokploy::route('/{record}/edit'),
        ];
    }
}
