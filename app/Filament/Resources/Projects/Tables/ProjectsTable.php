<?php

namespace App\Filament\Resources\Projects\Tables;

use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Project;
use App\Support\Jalali;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProjectsTable
{
    private const STATUS_COLORS = [
        'draft' => 'gray', 'planning' => 'info', 'in_progress' => 'warning',
        'delivered' => 'success', 'cancelled' => 'danger',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(fn (Project $record) => ProjectResource::getUrl('edit', ['record' => $record]))
            ->columns([
                TextColumn::make('code')->label(__('systems.code'))->searchable()->fontFamily('mono'),
                TextColumn::make('title')->label(__('systems.title'))->searchable()->weight('medium'),
                TextColumn::make('customer.name')->label(__('systems.customer'))->searchable(),
                TextColumn::make('systemVersion.systemModel.name')->label(__('systems.system_version'))->placeholder('—')->badge()->color('gray'),
                TextColumn::make('total_cost')
                    ->label(__('systems.total_cost'))
                    ->formatStateUsing(fn ($state) => $state > 0 ? Jalali::money($state) : '—')
                    ->extraHeaderAttributes(['class' => 'hidden lg:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden lg:table-cell']),
                TextColumn::make('status')
                    ->label(__('systems.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __("systems.statuses.$state"))
                    ->color(fn (string $state) => self::STATUS_COLORS[$state] ?? 'gray'),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('systems.status'))->options(__('systems.statuses')),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('systems.empty_projects'));
    }
}
