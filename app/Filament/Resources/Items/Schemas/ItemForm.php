<?php

namespace App\Filament\Resources\Items\Schemas;

use App\Models\ItemCategory;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
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

            FileUpload::make('image')
                ->label(__('items.image'))
                ->image()
                ->imageEditor()
                ->disk('items')
                ->visibility('public')
                ->maxSize(2048)
                ->columnSpanFull(),

            Textarea::make('description')->label(__('items.description'))->columnSpanFull()->rows(2),

            // مشخصات فنیِ اختیاری و کالا-محور: برای کالاهایی مثلِ کیس با فهرستِ
            // قطعات. با تیک روشن می‌شود و جدولِ عنوان/مقدار زیرش ظاهر می‌شود.
            Section::make(__('items.specs_section'))
                ->description(__('items.specs_section_hint'))
                ->columnSpanFull()
                ->schema([
                    Toggle::make('has_specs')
                        ->label(__('items.has_specs'))
                        ->helperText(__('items.has_specs_hint'))
                        ->live(),

                    Repeater::make('specs')
                        ->label(__('items.specs_table'))
                        ->addActionLabel(__('items.specs_add_row'))
                        ->visible(fn ($get) => (bool) $get('has_specs'))
                        ->columns(2)
                        ->defaultItems(1)
                        ->reorderable()
                        ->schema([
                            TextInput::make('label')
                                ->label(__('items.spec_row_label'))
                                ->placeholder(__('items.spec_row_label_ph'))
                                ->required(),
                            TextInput::make('value')
                                ->label(__('items.spec_row_value'))
                                ->placeholder(__('items.spec_row_value_ph'))
                                ->required(),
                        ]),
                ]),
        ])->columns(2);
    }
}
