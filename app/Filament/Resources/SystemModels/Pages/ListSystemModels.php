<?php

namespace App\Filament\Resources\SystemModels\Pages;

use App\Filament\Resources\SystemModels\SystemModelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSystemModels extends ListRecords
{
    protected static string $resource = SystemModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
