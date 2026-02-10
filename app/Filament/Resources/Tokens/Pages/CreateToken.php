<?php

namespace App\Filament\Resources\Tokens\Pages;

use App\Filament\Resources\Tokens\TokenResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateToken extends CreateRecord
{
    protected static string $resource = TokenResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $token = auth()->user()->createToken('auto_'.now()->timestamp);

        return $token->accessToken;
    }
}
