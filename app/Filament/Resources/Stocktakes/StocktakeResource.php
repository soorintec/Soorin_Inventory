<?php

namespace App\Filament\Resources\Stocktakes;

use App\Enums\Permission;
use App\Filament\Resources\Stocktakes\Pages\ListStocktakes;
use App\Filament\Resources\Stocktakes\Pages\ViewStocktake;
use App\Filament\Resources\Stocktakes\RelationManagers\LinesRelationManager;
use App\Models\Stocktake;
use App\Support\Jalali;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StocktakeResource extends Resource
{
    protected static ?string $model = Stocktake::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?int $navigationSort = 13;

    public static function getModelLabel(): string
    {
        return __('stocktake.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('stocktake.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('stocktake.nav_group');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(fn (Stocktake $record) => static::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('code')->label(__('stocktake.code'))->searchable()->weight('bold'),

                TextColumn::make('warehouse.name')->label(__('stocktake.warehouse'))->badge()->color('gray'),

                TextColumn::make('status')
                    ->label(__('stocktake.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __("stocktake.statuses.{$state}"))
                    ->color(fn (string $state) => match ($state) {
                        Stocktake::STATUS_CLOSED   => 'success',
                        Stocktake::STATUS_COUNTING => 'warning',
                        Stocktake::STATUS_CANCELLED => 'danger',
                        default                    => 'gray',
                    }),

                TextColumn::make('lines_count')
                    ->label(__('stocktake.total_lines'))
                    ->counts('lines')
                    ->formatStateUsing(fn ($state) => Jalali::quantity($state)),

                TextColumn::make('started_at')
                    ->label(__('stocktake.started_at'))
                    ->formatStateUsing(fn ($state) => Jalali::formatDateTime($state))
                    ->placeholder('—'),

                TextColumn::make('startedBy.name')->label(__('stocktake.started_by'))->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('stocktake.status'))->options(__('stocktake.statuses')),
                SelectFilter::make('warehouse_id')->label(__('stocktake.warehouse'))->relationship('warehouse', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
                // حذف یک دوره انبارگردانی — نرم (soft delete)، پس سابقه در
                // دیتابیس می‌ماند. حرکات انباری که هنگام «به‌روزرسانی انبار»
                // ثبت شده باشند دست‌نخورده می‌مانند.
                DeleteAction::make()
                    ->visible(fn () => auth()->user()?->can(Permission::ManageStocktakes->value) ?? false),
            ])
            ->defaultSort('id', 'desc')
            ->emptyStateHeading(__('stocktake.empty'))
            ->emptyStateDescription(__('stocktake.intro'));
    }

    public static function getRelations(): array
    {
        return [LinesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStocktakes::route('/'),
            'view'  => ViewStocktake::route('/{record}'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permission::ViewStock->value) ?? false;
    }

    public static function canCreate(): bool
    {
        // ساخت فقط از دکمه «شروع انبارگردانی» انجام می‌شود تا فهرست شمارش
        // هم‌زمان ساخته شود؛ رکورد خالی به درد نمی‌خورد.
        return false;
    }

    public static function canEdit(mixed $record): bool
    {
        return false;
    }

    public static function canDelete(mixed $record): bool
    {
        return auth()->user()?->can(Permission::ManageStocktakes->value) ?? false;
    }
}
