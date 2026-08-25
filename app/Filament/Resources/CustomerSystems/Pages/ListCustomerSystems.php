<?php

namespace App\Filament\Resources\CustomerSystems\Pages;

use App\Filament\Resources\CustomerSystems\CustomerSystemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCustomerSystems extends ListRecords
{
    protected static string $resource = CustomerSystemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
