<?php

namespace App\Filament\Resources\SystemModels\RelationManagers;

use App\Enums\Permission;
use App\Filament\Resources\SystemVersions\SystemVersionResource;
use App\Models\Item;
use App\Models\ItemVersion;
use App\Models\SystemVersion;
use App\Support\Jalali;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * نسخه‌های یک مدل سامانه.
 *
 * لیست قطعات (BOM) عمداً اینجا نیست: قبلاً یک Repeater داخل همین پنجره بود و
 * کسی پیدایش نمی‌کرد، ضمن اینکه در پنجره کوچک جا برای نشان دادن موجودی و
 * کسری هر قطعه نبود. حالا هر نسخه صفحه کامل خودش را دارد.
 */
class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('systems.version_plural');
    }

    /**
     * فیلامنت relation managerهای روی صفحه View را پیش‌فرض فقط‌خواندنی می‌کند.
     * مدل سامانه صفحه Edit جدا برای نسخه‌ها ندارد و همین صفحه خانهٔ افزودن
     * نسخه و قطعاتش است — پس باید نوشتنی باشد. کنترل دسترسی واقعی روی مجوز
     * ManageSystemModels در خود اکشن‌هاست. (باگ «به قطعات دسترسی ندارم».)
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('version_code')
                    ->label(__('systems.version_code'))
                    ->helperText(__('systems.version_code_hint'))
                    ->required()
                    ->maxLength(40),
                TextInput::make('year')->label(__('systems.year'))->numeric(),
                Toggle::make('is_active')->label(__('common.active'))->default(true),
                Textarea::make('notes')->label(__('systems.notes'))->rows(2)->columnSpanFull(),

                // انتخاب قطعات همین‌جا هنگام ساخت/ویرایش نسخه — تا لازم نباشد
                // اول نسخه را بسازی و بعد جداگانه دنبال دکمه قطعات بگردی. مدیریت
                // کامل‌تر (موجودی، کسری، قیمت) در صفحه «قطعات» همان نسخه هست.
                Repeater::make('bomLines')
                    ->relationship()
                    ->label(__('systems.bom'))
                    ->addActionLabel(__('systems.bom_add'))
                    ->columnSpanFull()
                    ->defaultItems(0)
                    ->columns(4)
                    ->schema([
                        Select::make('item_id')
                            ->label(__('systems.bom_item'))
                            ->options(fn () => Item::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn ($set) => $set('item_version_id', null))
                            ->columnSpan(2),

                        Select::make('item_version_id')
                            ->label(__('systems.bom_version'))
                            ->placeholder(__('systems.bom_any_version'))
                            ->options(fn ($get) => $get('item_id')
                                ? ItemVersion::where('item_id', $get('item_id'))->orderBy('version_code')->pluck('version_code', 'id')
                                : [])
                            ->native(false),

                        TextInput::make('quantity')
                            ->label(__('systems.bom_quantity'))
                            ->numeric()
                            ->minValue(0.01)
                            ->default(1)
                            ->required(),

                        Toggle::make('is_optional')
                            ->label(__('systems.bom_optional'))
                            ->inline(false),
                    ]),
            ])
            ->columns(3);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('version_code')
            ->recordUrl(fn (SystemVersion $record) => SystemVersionResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('version_code')->label(__('systems.version_code'))->weight('medium'),

                TextColumn::make('year')
                    ->label(__('systems.year'))
                    ->placeholder('—')
                    ->formatStateUsing(fn ($state) => $state ? Jalali::digits((string) $state) : null),

                TextColumn::make('bom_lines_count')
                    ->label(__('systems.bom_count'))
                    ->counts('bomLines')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'warning')
                    ->formatStateUsing(fn ($state) => Jalali::quantity($state)),

                TextColumn::make('buildable')
                    ->label(__('systems.bom_buildable'))
                    ->state(fn (SystemVersion $record) => Jalali::quantity($record->buildableUnits()))
                    ->color(fn (SystemVersion $record) => $record->buildableUnits() > 0 ? 'success' : 'danger')
                    ->extraHeaderAttributes(['class' => 'hidden md:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden md:table-cell']),

                IconColumn::make('is_active')->label(__('common.active'))->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('systems.version_add'))
                    ->visible(fn () => auth()->user()?->can(Permission::ManageSystemModels->value) ?? false),
            ])
            ->recordActions([
                // «قطعات» برای دیدن هم کافی است (صفحه برای بازدیدکننده فقط‌خواندنی
                // است)، پس به مجوز مدیریت مشروط نشده.
                Action::make('parts')
                    ->label(__('systems.bom_open'))
                    ->icon(Heroicon::OutlinedListBullet)
                    ->color('primary')
                    ->url(fn (SystemVersion $record) => SystemVersionResource::getUrl('view', ['record' => $record])),
                EditAction::make()
                    ->visible(fn () => auth()->user()?->can(Permission::ManageSystemModels->value) ?? false),
                DeleteAction::make()
                    ->visible(fn () => auth()->user()?->can(Permission::ManageSystemModels->value) ?? false),
            ])
            ->emptyStateHeading(__('systems.empty_versions'))
            ->emptyStateDescription(__('systems.empty_versions_hint'));
    }
}
