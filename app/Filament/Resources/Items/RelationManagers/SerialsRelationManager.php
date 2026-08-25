<?php

namespace App\Filament\Resources\Items\RelationManagers;

use App\Enums\Permission;
use App\Models\Item;
use App\Models\ItemSerial;
use App\Models\ItemVersion;
use App\Models\Warehouse;
use App\Support\Jalali;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * شماره سریال اقلام گران.
 *
 * فقط وقتی دیده می‌شود که روی کالا تیک «ثبت سریال» خورده باشد — تا صفحه
 * کالاهای معمولی (کابل و پیچ) بی‌خود شلوغ نشود.
 *
 * ورود دسته‌ای هم دارد، چون سریال‌ها معمولاً با هم و از روی یک بارنامه وارد
 * می‌شوند و یکی‌یکی زدنشان برای ۹۳ عدد هارد شکنجه است.
 */
class SerialsRelationManager extends RelationManager
{
    protected static string $relationship = 'serials';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('items.serials');
    }

    /** فقط برای کالایی که تیک «ثبت سریال» دارد. */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) $ownerRecord->track_serial;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('item_version_id')
                    ->label(__('items.version_label'))
                    ->options(fn () => $this->versionOptions())
                    ->required()
                    ->native(false),

                TextInput::make('serial')
                    ->label(__('items.serial'))
                    ->required()
                    ->maxLength(80)
                    // سریال باید در دامنه همان ورژن یکتا باشد؛ ایندکس یکتای
                    // دیتابیس هم همین را می‌گوید و اینجا پیش از خطای SQL می‌گیریمش
                    ->rule(fn (?ItemSerial $record) => function ($attribute, $value, $fail) use ($record) {
                        $exists = ItemSerial::query()
                            ->whereIn('item_version_id', $this->versionIds())
                            ->where('serial', $value)
                            ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))
                            ->exists();

                        if ($exists) {
                            $fail(__('items.serial_duplicate'));
                        }
                    }),

                Select::make('warehouse_id')
                    ->label(__('stock.warehouse'))
                    ->options(fn () => Warehouse::where('is_active', true)->pluck('name', 'id'))
                    ->native(false),

                Select::make('status')
                    ->label(__('items.serial_status'))
                    ->options(__('items.serial_statuses'))
                    ->default(ItemSerial::STATUS_IN_STOCK)
                    ->required()
                    ->native(false),

                DatePicker::make('supplier_warranty_until')
                    ->label(__('items.warranty_until'))
                    ->helperText(__('items.warranty_hint')),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('serial')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['itemVersion', 'warehouse']))
            ->defaultSort('item_serials.serial')
            ->columns([
                TextColumn::make('serial')
                    ->label(__('items.serial'))
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->copyable(),

                TextColumn::make('itemVersion.version_code')
                    ->label(__('items.version_label'))
                    ->badge()
                    ->color('gray'),

                TextColumn::make('status')
                    ->label(__('items.serial_status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __("items.serial_statuses.{$state}"))
                    ->color(fn (string $state) => match ($state) {
                        ItemSerial::STATUS_IN_STOCK  => 'success',
                        ItemSerial::STATUS_INSTALLED => 'info',
                        ItemSerial::STATUS_DEFECTIVE => 'danger',
                        default                      => 'gray',
                    }),

                TextColumn::make('warehouse.name')
                    ->label(__('stock.warehouse'))
                    ->placeholder('—')
                    ->extraHeaderAttributes(['class' => 'hidden md:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden md:table-cell']),

                TextColumn::make('supplier_warranty_until')
                    ->label(__('items.warranty_until'))
                    ->placeholder('—')
                    ->formatStateUsing(fn ($state) => Jalali::format($state))
                    ->color(fn ($state) => $state && $state->isPast() ? 'danger' : null)
                    ->extraHeaderAttributes(['class' => 'hidden lg:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden lg:table-cell']),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('items.serial_status'))
                    ->options(__('items.serial_statuses')),
            ])
            ->headerActions([
                CreateAction::make()->label(__('items.serial_add'))->visible(fn () => $this->canManage()),
                $this->bulkAddAction(),
            ])
            ->recordActions([
                EditAction::make()->visible(fn () => $this->canManage()),
                DeleteAction::make()->visible(fn () => $this->canManage()),
            ])
            ->toolbarActions([
                DeleteBulkAction::make()->visible(fn () => $this->canManage()),
            ])
            ->emptyStateHeading(__('items.serials_empty'))
            ->emptyStateDescription(__('items.serials_empty_hint'))
            ->paginated([25, 50, 100, 200]);
    }

    /** ورود دسته‌ای: هر سطر یک سریال. */
    private function bulkAddAction(): Action
    {
        return Action::make('bulkAdd')
            ->label(__('items.serial_bulk_add'))
            ->icon('heroicon-o-queue-list')
            ->visible(fn () => $this->canManage())
            ->schema([
                Select::make('item_version_id')
                    ->label(__('items.version_label'))
                    ->options(fn () => $this->versionOptions())
                    ->required()
                    ->native(false),

                Select::make('warehouse_id')
                    ->label(__('stock.warehouse'))
                    ->options(fn () => Warehouse::where('is_active', true)->pluck('name', 'id'))
                    ->native(false),

                Textarea::make('serials')
                    ->label(__('items.serial_bulk_list'))
                    ->helperText(__('items.serial_bulk_hint'))
                    ->rows(10)
                    ->required()
                    ->columnSpanFull(),
            ])
            ->action(function (array $data): void {
                $lines = collect(preg_split('/[\r\n,]+/', $data['serials']))
                    ->map(fn (string $line) => trim($line))
                    ->filter()
                    ->unique()
                    ->values();

                $existing = ItemSerial::where('item_version_id', $data['item_version_id'])
                    ->whereIn('serial', $lines)
                    ->pluck('serial')
                    ->all();

                $new = $lines->reject(fn (string $s) => in_array($s, $existing, true));

                DB::transaction(function () use ($new, $data) {
                    foreach ($new as $serial) {
                        ItemSerial::create([
                            'item_version_id' => $data['item_version_id'],
                            'warehouse_id'    => $data['warehouse_id'] ?? null,
                            'serial'          => $serial,
                            'status'          => ItemSerial::STATUS_IN_STOCK,
                        ]);
                    }
                });

                \Filament\Notifications\Notification::make()
                    ->title(__('items.serial_bulk_done', [
                        'added'   => Jalali::quantity($new->count()),
                        'skipped' => Jalali::quantity(count($existing)),
                    ]))
                    ->success()
                    ->send();
            });
    }

    /** @return array<int, string> */
    private function versionOptions(): array
    {
        /** @var Item $item */
        $item = $this->getOwnerRecord();

        return $item->versions()->orderBy('version_code')->pluck('version_code', 'id')->all();
    }

    /** @return array<int, int> */
    private function versionIds(): array
    {
        return $this->getOwnerRecord()->versions()->pluck('id')->all();
    }

    private function canManage(): bool
    {
        return auth()->user()?->can(Permission::ManageItems->value) ?? false;
    }
}
