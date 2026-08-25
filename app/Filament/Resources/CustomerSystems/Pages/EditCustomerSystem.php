<?php

namespace App\Filament\Resources\CustomerSystems\Pages;

use App\Filament\Resources\CustomerSystems\CustomerSystemResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCustomerSystem extends EditRecord
{
    protected static string $resource = CustomerSystemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
