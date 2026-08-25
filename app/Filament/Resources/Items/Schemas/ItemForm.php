<?php

namespace App\Filament\Resources\Items\Schemas;

use App\Models\ItemCategory;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('item_category_id')
                ->label(__('items.category_label'))
                ->options(fn () => ItemCategory::whereNotNull('parent_id')
                    ->orWhereDoesntHave('children')
                    ->with('parent')
                    ->get()
                    ->mapWithKeys(fn (ItemCategory $c) => [$c->id => $c->parent ? "{$c->parent->name} ← {$c->name}" : $c->name]))
                ->searchable()
                ->required(),

            TextInput::make('code')->label(__('items.code'))->required()->maxLength(30)->unique(ignoreRecord: true),
            TextInput::make('name')->label(__('items.name'))->required()->maxLength(255),
            TextInput::make('brand')->label(__('items.brand'))->maxLength(60),
            TextInput::make('unit')->label(__('items.unit'))->default(__('items.unit_default'))->required(),

            Toggle::make('track_serial')
                ->label(__('items.track_serial'))
                ->helperText(__('items.track_serial_hint')),

            Toggle::make('is_active')->label(__('common.active'))->default(true),

            Textarea::make('description')->label(__('items.description'))->columnSpanFull()->rows(2),
        ])->columns(2);
    }
}
