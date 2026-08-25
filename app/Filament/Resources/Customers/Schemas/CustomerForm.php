<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')
                ->label(__('customers.code'))
                ->required()
                ->maxLength(20)
                ->unique(ignoreRecord: true)
                ->helperText(__('customers.code_hint')),

            TextInput::make('name')->label(__('customers.name'))->required()->maxLength(255),

            Select::make('entity_type')
                ->label(__('customers.entity_type'))
                ->options(__('customers.entity_types'))
                ->default('company')
                ->required()
                ->native(false),

            TextInput::make('phone')->label(__('common.phone'))->maxLength(30),
            TextInput::make('mobile')->label(__('common.mobile'))->maxLength(20),
            TextInput::make('email')->label(__('common.email'))->email()->maxLength(255),
            TextInput::make('city')->label(__('common.city'))->maxLength(80),
            Textarea::make('address')->label(__('common.address'))->rows(2)->columnSpanFull(),
            Textarea::make('notes')->label(__('common.notes'))->rows(2)->columnSpanFull(),
        ])->columns(2);
    }
}
