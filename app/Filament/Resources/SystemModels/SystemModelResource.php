<?php

namespace App\Filament\Resources\SystemModels;

use App\Enums\Permission;
use App\Filament\Resources\SystemModels\Pages\ListSystemModels;
use App\Filament\Resources\SystemModels\RelationManagers\VersionsRelationManager;
use App\Models\SystemModel;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * مدل سامانه (نقشه) — مثال: Titan S2. نسخه‌ها و BOM از طریق صفحه نمایش
 * مدیریت می‌شوند. این با «سامانه اجراشده» فرق دارد؛ تغییر اینجا سوابق
 * نصب‌شده را عوض نمی‌کند.
 */
class SystemModelResource extends Resource
{
    protected static ?string $model = SystemModel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static ?int $navigationSort = 30;

    public static function getModelLabel(): string
    {
        return __('systems.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('systems.model_plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('systems.nav_group');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->label(__('systems.model_code'))->required()->maxLength(30)->unique(ignoreRecord: true),
            TextInput::make('name')->label(__('systems.model_name'))->required()->maxLength(255)
                ->helperText(__('systems.model_hint')),
            Toggle::make('is_active')->label(__('common.active'))->default(true),
            Textarea::make('description')->label(__('systems.description'))->rows(2)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(fn (SystemModel $record) => SystemModelResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('code')->label(__('systems.model_code'))->searchable(),
                TextColumn::make('name')->label(__('systems.model_name'))->searchable()->weight('medium'),
                TextColumn::make('versions_count')->label(__('systems.version_plural'))->counts('versions')->badge(),

                // تعداد کل قطعات همه نسخه‌ها — یک نگاه می‌گوید BOM پر شده یا نه
                TextColumn::make('bom_lines_count')
                    ->label(__('systems.bom_count'))
                    ->counts('bomLines')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'warning')
                    ->formatStateUsing(fn ($state) => \App\Support\Jalali::quantity($state)),

                IconColumn::make('is_active')->label(__('common.active'))->boolean(),
            ])
            ->recordActions([ViewAction::make(), EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading(__('systems.empty_models'))
            ->emptyStateDescription(__('systems.model_hint'));
    }

    public static function getRelations(): array
    {
        return [VersionsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSystemModels::route('/'),
            'view'  => \App\Filament\Resources\SystemModels\Pages\ViewSystemModel::route('/{record}'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permission::ViewProjects->value) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can(Permission::ManageSystemModels->value) ?? false;
    }

    public static function canEdit(mixed $record): bool
    {
        return auth()->user()?->can(Permission::ManageSystemModels->value) ?? false;
    }

    public static function canDelete(mixed $record): bool
    {
        return auth()->user()?->can(Permission::ManageSystemModels->value) ?? false;
    }
}
