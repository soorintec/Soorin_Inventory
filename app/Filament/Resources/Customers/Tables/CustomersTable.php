<?php

namespace App\Filament\Resources\Customers\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label(__('customers.code'))->searchable()->sortable(),
                TextColumn::make('name')->label(__('customers.name'))->searchable()->weight('medium'),
                TextColumn::make('city')->label(__('common.city'))->placeholder('—'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading(__('customers.empty'));
    }
}
