<?php

namespace App\Filament\Resources\CustomerSystems\Tables;

use App\Filament\Resources\CustomerSystems\CustomerSystemResource;
use App\Models\CustomerSystem;
use App\Support\Jalali;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CustomerSystemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(fn (CustomerSystem $record) => CustomerSystemResource::getUrl('edit', ['record' => $record]))
            ->columns([
                TextColumn::make('code')->label(__('systems.cs_code'))->searchable()->fontFamily('mono'),
                TextColumn::make('name')->label(__('systems.cs_name'))->searchable()->weight('medium'),
                TextColumn::make('customer.name')->label(__('systems.customer'))->searchable(),
                TextColumn::make('installed_at')
                    ->label(__('systems.installed_at'))
                    ->formatStateUsing(fn ($state) => $state ? Jalali::format($state) : '—'),
                TextColumn::make('total_cost')
                    ->label(__('systems.total_cost'))
                    ->formatStateUsing(fn ($state) => $state > 0 ? Jalali::money($state) : '—')
                    ->extraHeaderAttributes(['class' => 'hidden lg:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden lg:table-cell']),
                TextColumn::make('status')
                    ->label(__('systems.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __("systems.cs_status.$state"))
                    ->color(fn (string $state) => $state === 'active' ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('customer_id')->label(__('systems.customer'))->relationship('customer', 'name'),
                SelectFilter::make('status')->label(__('systems.status'))->options(__('systems.cs_status')),
            ])
            ->defaultSort('installed_at', 'desc')
            ->emptyStateHeading(__('systems.empty_systems'));
    }
}
