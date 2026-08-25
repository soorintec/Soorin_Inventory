<?php

namespace App\Filament\Resources\SystemVersions\RelationManagers;

use App\Enums\Permission;
use App\Models\Item;
use App\Models\ItemVersion;
use App\Models\SystemBomLine;
use App\Support\Jalali;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * لیست قطعات استاندارد (BOM) یک نسخه سامانه — «برای ساختن یک دستگاه از این
 * نسخه، چه قطعاتی و چند تا لازم است».
 *
 * این جدول عمداً موجودی انبار را هم کنار هر قطعه نشان می‌دهد، چون سؤال واقعی
 * کاربر همیشه ترکیب این دو است: «چه لازم دارم و چقدرش را دارم».
 */
class BomRelationManager extends RelationManager
{
    protected static string $relationship = 'bomLines';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('systems.bom');
    }

    /**
     * فیلامنت relation managerهای روی صفحه View را به‌طور پیش‌فرض فقط‌خواندنی
     * می‌کند (چون معمولاً صفحه Edit جدایی هست). اینجا صفحه Edit جدا وجود ندارد
     * و همین صفحه، خانهٔ ویرایش قطعات است — پس باید نوشتنی باشد.
     * کنترل دسترسی واقعی روی مجوز ManageSystemModels است، نه اینجا.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('item_id')
                    ->label(__('systems.bom_item'))
                    ->options(fn () => Item::query()
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    // عوض شدن کالا، ورژنِ انتخاب‌شده قبلی را بی‌معنی می‌کند
                    ->afterStateUpdated(fn ($set) => $set('item_version_id', null))
                    ->columnSpan(2),

                Select::make('item_version_id')
                    ->label(__('systems.bom_version'))
                    ->helperText(__('systems.bom_version_hint'))
                    ->options(fn ($get) => $get('item_id')
                        ? ItemVersion::where('item_id', $get('item_id'))
                            ->orderBy('version_code')
                            ->pluck('version_code', 'id')
                        : [])
                    ->searchable()
                    ->native(false),

                TextInput::make('quantity')
                    ->label(__('systems.bom_quantity'))
                    ->helperText(__('systems.bom_quantity_hint'))
                    ->numeric()
                    ->minValue(0.01)
                    ->default(1)
                    ->required(),

                Toggle::make('is_optional')
                    ->label(__('systems.bom_optional'))
                    ->helperText(__('systems.bom_optional_hint')),

                Textarea::make('notes')
                    ->label(__('systems.notes'))
                    ->rows(2)
                    ->columnSpanFull(),
            ])
            ->columns(3);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['item.category', 'itemVersion']))
            ->defaultSort('id')
            ->columns([
                TextColumn::make('item.name')
                    ->label(__('systems.bom_item'))
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn (SystemBomLine $record) => $record->item?->code),

                TextColumn::make('item.category.name')
                    ->label(__('items.category'))
                    ->badge()
                    ->color('gray')
                    ->extraHeaderAttributes(['class' => 'hidden lg:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden lg:table-cell']),

                TextColumn::make('itemVersion.version_code')
                    ->label(__('systems.bom_version'))
                    ->placeholder(__('systems.bom_any_version'))
                    ->extraHeaderAttributes(['class' => 'hidden md:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden md:table-cell']),

                TextColumn::make('quantity')
                    ->label(__('systems.bom_quantity'))
                    ->formatStateUsing(fn ($state) => Jalali::quantity($state))
                    ->suffix(fn (SystemBomLine $record) => ' ' . ($record->item?->unit ?? ''))
                    ->weight('bold')
                    ->sortable(),

                // موجودی و کسری محاسبه‌ای‌اند، پس مرتب‌سازی روی دیتابیس ندارند
                TextColumn::make('stock')
                    ->label(__('systems.bom_in_stock'))
                    ->state(fn (SystemBomLine $record) => Jalali::quantity($record->currentStock()))
                    ->color(fn (SystemBomLine $record) => $record->shortage() > 0 ? 'danger' : 'success'),

                TextColumn::make('shortage')
                    ->label(__('systems.bom_shortage'))
                    ->state(fn (SystemBomLine $record) => $record->shortage() > 0
                        ? Jalali::quantity($record->shortage())
                        : '—')
                    ->color('danger')
                    ->weight('bold'),

                TextColumn::make('unit_cost')
                    ->label(__('systems.bom_unit_cost'))
                    ->state(fn (SystemBomLine $record) => $record->unitCost() > 0
                        ? Jalali::money($record->unitCost())
                        : '—')
                    ->extraHeaderAttributes(['class' => 'hidden xl:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden xl:table-cell']),

                TextColumn::make('line_cost')
                    ->label(__('systems.bom_line_cost'))
                    ->state(fn (SystemBomLine $record) => $record->lineCost() > 0
                        ? Jalali::money($record->lineCost())
                        : '—')
                    ->extraHeaderAttributes(['class' => 'hidden xl:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden xl:table-cell']),

                IconColumn::make('is_optional')
                    ->label(__('systems.bom_optional'))
                    ->boolean()
                    ->trueIcon('heroicon-o-minus-circle')
                    ->trueColor('warning')
                    ->falseIcon('heroicon-o-check-circle')
                    ->falseColor('gray'),
            ])
            ->filters([
                SelectFilter::make('item_category')
                    ->label(__('items.category'))
                    ->relationship('item.category', 'name'),

                // فیلتر «فقط کسری‌دارها» عمداً نیست: کسری از مقایسه با موجودی
                // چند انبار محاسبه می‌شود و ستون دیتابیس نیست، پس فیلتر SQL
                // برایش دروغ درمی‌آید. ستون «کسری» رنگی است و چشم خودش پیدا می‌کند.
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('systems.bom_add'))
                    ->visible(fn () => auth()->user()?->can(Permission::ManageSystemModels->value) ?? false),
            ])
            ->recordActions([
                EditAction::make()->visible(fn () => auth()->user()?->can(Permission::ManageSystemModels->value) ?? false),
                DeleteAction::make()->visible(fn () => auth()->user()?->can(Permission::ManageSystemModels->value) ?? false),
            ])
            ->toolbarActions([
                DeleteBulkAction::make()->visible(fn () => auth()->user()?->can(Permission::ManageSystemModels->value) ?? false),
            ])
            ->emptyStateHeading(__('systems.bom_empty'))
            ->emptyStateDescription(__('systems.bom_empty_hint'))
            ->paginated([25, 50, 100, 200]);
    }
}
