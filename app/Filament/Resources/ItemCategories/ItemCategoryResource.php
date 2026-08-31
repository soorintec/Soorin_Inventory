<?php

namespace App\Filament\Resources\ItemCategories;

use App\Enums\Permission;
use App\Filament\Resources\ItemCategories\Pages\ListItemCategories;
use App\Models\ItemCategory;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * دسته کالا — سطح اول ساختار سه‌سطحی.
 * spec_template اینجا تعریف می‌شود و در فرم ورژن کالا (سطح سوم) استفاده می‌شود.
 */
class ItemCategoryResource extends Resource
{
    protected static ?string $model = ItemCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 10;

    /**
     * از منو برداشته شد: دسته‌بندی بخش مستقلی نیست، فقط راهی برای گروه‌بندی
     * کالاست. ساخت و ویرایشش از «مدیریت انبار» انجام می‌شود و برای پیدا کردن
     * کالا، فیلتر دسته در «موجودی انبار» هست. صفحه‌اش سر جایش می‌ماند تا
     * لینک‌ها و دکمه‌های موجود کار کنند.
     */
    protected static bool $shouldRegisterNavigation = false;

    public static function getModelLabel(): string
    {
        return __('items.category_label');
    }

    // بدون این، فیلامنت خودش «s» انگلیسی به «دسته کالا» می‌چسباند («دسته کالاs»).
    public static function getPluralModelLabel(): string
    {
        return __('items.category_plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('items.category_plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('items.nav_group');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('parent_id')
                ->label(__('items.category_parent'))
                ->options(fn () => ItemCategory::whereNull('parent_id')->pluck('name', 'id'))
                ->native(false),

            TextInput::make('name')
                ->label(__('common.name'))
                ->required()
                ->maxLength(100),

            TextInput::make('code')
                ->label(__('items.code'))
                ->maxLength(20),

            Section::make(__('items.spec_template'))
                ->description(__('items.spec_template_hint'))
                ->schema([
                    Repeater::make('spec_template')
                        ->hiddenLabel()
                        ->schema([
                            TextInput::make('key')
                                ->label(__('items.spec_key'))
                                ->required()
                                ->alphaDash(),
                            TextInput::make('label')
                                ->label(__('items.spec_label'))
                                ->required(),
                        ])
                        ->columns(2)
                        ->addActionLabel(__('common.create'))
                        ->reorderable(false),
                ]),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('parent.name')
                    ->label(__('items.category_parent'))
                    ->placeholder('—')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('name')
                    ->label(__('common.name'))
                    ->searchable()
                    ->weight('medium'),

                TextColumn::make('code')
                    ->label(__('items.code'))
                    ->placeholder('—'),

                TextColumn::make('items_count')
                    ->label(__('items.plural'))
                    ->counts('items')
                    ->badge()
                    // کلیک روی عدد → فهرستِ کالاهای همین دسته در «موجودی انبار».
                    ->url(fn ($record) => $record->items_count > 0
                        ? \App\Filament\Resources\Items\ItemResource::getUrl('index', ['category' => $record->id])
                        : null),
            ])
            ->recordActions([
                EditAction::make(),
                // حذفِ دسته‌ای که کالا یا زیردسته دارد جلوگیری می‌شود (وگرنه خطای
                // کلید خارجی می‌داد) و پیامِ روشن نشان داده می‌شود.
                DeleteAction::make()
                    ->before(function (ItemCategory $record, DeleteAction $action) {
                        if ($record->items()->exists() || $record->children()->exists()) {
                            Notification::make()
                                ->danger()
                                ->title(__('items.category_in_use'))
                                ->persistent()
                                ->send();

                            $action->halt();
                        }
                    }),
            ])
            ->emptyStateHeading(__('items.empty_categories'));
    }

    public static function getPages(): array
    {
        return ['index' => ListItemCategories::route('/')];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permission::ViewItems->value) ?? false;
    }
}
