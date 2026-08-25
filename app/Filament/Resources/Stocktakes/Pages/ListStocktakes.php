<?php

namespace App\Filament\Resources\Stocktakes\Pages;

use App\Enums\Permission;
use App\Filament\Resources\Stocktakes\StocktakeResource;
use App\Models\Warehouse;
use App\Services\StocktakeService;
use App\Support\Jalali;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListStocktakes extends ListRecords
{
    protected static string $resource = StocktakeResource::class;

    public function getSubheading(): ?string
    {
        return __('stocktake.intro');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('start')
                ->label(__('stocktake.start'))
                ->icon(Heroicon::OutlinedPlay)
                ->color('primary')
                ->modalHeading(__('stocktake.start_heading'))
                ->visible(fn () => auth()->user()?->can(Permission::ManageStocktakes->value) ?? false)
                ->schema([
                    Select::make('warehouse_id')
                        ->label(__('stocktake.warehouse'))
                        ->helperText(__('stocktake.start_hint'))
                        ->options(fn () => Warehouse::where('is_active', true)->pluck('name', 'id'))
                        ->default(fn () => Warehouse::where('code', 'MAIN')->value('id'))
                        ->required()
                        ->native(false),

                    Textarea::make('notes')->label(__('stocktake.notes'))->rows(2),
                ])
                ->action(function (array $data, StocktakeService $service) {
                    $stocktake = $service->start(
                        Warehouse::findOrFail($data['warehouse_id']),
                        $data['notes'] ?? null,
                    );

                    Notification::make()
                        ->success()
                        ->title(__('stocktake.started', [
                            'code'  => $stocktake->code,
                            'lines' => Jalali::quantity($stocktake->lines()->count()),
                        ]))
                        ->send();

                    $this->redirect(StocktakeResource::getUrl('view', ['record' => $stocktake]));
                }),
        ];
    }
}
