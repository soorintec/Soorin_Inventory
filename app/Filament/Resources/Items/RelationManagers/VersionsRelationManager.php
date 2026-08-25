<?php

namespace App\Filament\Resources\Items\RelationManagers;

use App\Models\Item;
use App\Models\ItemVersion;
use App\Support\Jalali;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * ورژن‌های یک کالا — سطح سوم و جایی که موجودی و قیمت واقعاً ثبت می‌شود.
 *
 * فرم مشخصات فنی (specs) به‌صورت پویا از spec_template دسته همین کالا
 * ساخته می‌شود — نه فیلد ثابت، نه آزاد بی‌ساختار.
 */
class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('items.version_plural');
    }

    public function form(Schema $schema): Schema
    {
        /** @var Item $item */
        $item = $this->getOwnerRecord();
        $specTemplate = $item->category?->spec_template ?? [];

        $fields = [
            TextInput::make('version_code')->label(__('items.version_code'))->required()->maxLength(40),
            TextInput::make('name')->label(__('items.version_name'))->maxLength(255),
            TextInput::make('location')->label(__('items.location'))
                ->helperText(__('items.location_hint'))->maxLength(60),
            TextInput::make('year')->label(__('items.year'))->numeric(),
            TextInput::make('min_stock')->label(__('items.min_stock'))->helperText(__('items.min_stock_hint'))->numeric()->default(0),
            TextInput::make('fx_price')
                ->label(__('items.fx_price'))
                ->helperText(__('items.fx_price_hint'))
                ->numeric()
                ->minValue(0),
            Select::make('fx_currency')
                ->label(__('items.fx_currency'))
                ->options(\App\Models\Currency::options())
                ->default('IRR')
                ->selectablePlaceholder(false)
                ->native(false),
            Textarea::make('notes')->label(__('items.notes'))
                ->helperText(__('items.notes_hint'))->rows(2)->columnSpanFull(),
        ];

        // فیلد پویا برای هر کلید تعریف‌شده در قالب مشخصات فنی دسته
        foreach ($specTemplate as $spec) {
            if (empty($spec['key'])) {
                continue;
            }

            $fields[] = TextInput::make("specs.{$spec['key']}")
                ->label($spec['label'] ?? $spec['key']);
        }

        return $schema->components($fields)->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('version_code')
            ->columns([
                TextColumn::make('version_code')->label(__('items.version_code'))->weight('medium'),
                TextColumn::make('name')->label(__('items.version_name'))->placeholder('—'),
                TextColumn::make('location')->label(__('items.location'))->placeholder('—')->badge()->color('gray'),

                // یادداشت‌ها بلندند؛ کوتاه نمایش داده و کاملش در tooltip می‌آید
                TextColumn::make('notes')->label(__('items.notes'))->placeholder('—')
                    ->limit(40)
                    ->tooltip(fn (?string $state) => $state)
                    ->wrap(),

                TextColumn::make('year')->label(__('items.year'))->placeholder('—')
                    ->formatStateUsing(fn ($state) => $state ? Jalali::digits((string) $state) : null),

                TextColumn::make('current_stock')
                    ->label(__('items.current_stock'))
                    ->state(fn (ItemVersion $record) => Jalali::quantity($record->totalQuantity())),

                TextColumn::make('fx_price')
                    ->label(__('items.fx_price'))
                    ->placeholder('—')
                    ->state(fn (ItemVersion $record) => $record->fxPriceLabel()),

                IconColumn::make('below_min')
                    ->label(__('items.below_min'))
                    ->state(fn (ItemVersion $record) => $record->isBelowMinStock())
                    ->boolean()
                    ->trueColor('danger')
                    ->trueIcon('heroicon-o-exclamation-triangle'),

                IconColumn::make('is_active')->label(__('common.active'))->boolean(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading(__('items.empty_versions'));
    }
}
