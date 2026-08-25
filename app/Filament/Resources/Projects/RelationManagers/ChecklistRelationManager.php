<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Support\Jalali;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * چک‌لیست پروژه — فقط‌خواندنی. با اکشن «تولید چک‌لیست» در صفحه پروژه
 * از روی BOM ساخته و با موجودی تطبیق داده می‌شود.
 */
class ChecklistRelationManager extends RelationManager
{
    protected static string $relationship = 'checklistLines';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('systems.checklist');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('item.name')->label(__('systems.checklist_item'))->weight('medium'),
                TextColumn::make('itemVersion.version_code')->label(__('items.version_label'))->placeholder('—'),
                TextColumn::make('quantity_required')->label(__('systems.required'))->formatStateUsing(fn ($s) => Jalali::quantity($s)),
                TextColumn::make('quantity_reserved')->label(__('systems.reserved'))->formatStateUsing(fn ($s) => Jalali::quantity($s))->color('success'),
                TextColumn::make('quantity_shortage')
                    ->label(__('systems.shortage'))
                    ->formatStateUsing(fn ($s) => Jalali::quantity($s))
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'gray')
                    ->weight('bold'),
                TextColumn::make('status')
                    ->label(__('systems.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __("systems.line_status.$state"))
                    ->color(fn (string $state) => $state === 'purchase_needed' ? 'danger' : ($state === 'reserved' ? 'success' : 'gray')),
            ])
            ->emptyStateHeading(__('systems.checklist_empty'));
    }
}
