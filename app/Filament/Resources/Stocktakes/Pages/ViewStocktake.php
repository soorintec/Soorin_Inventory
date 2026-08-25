<?php

namespace App\Filament\Resources\Stocktakes\Pages;

use App\Enums\Permission;
use App\Filament\Resources\Stocktakes\StocktakeResource;
use App\Models\Stocktake;
use App\Services\StocktakeService;
use App\Support\Jalali;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class ViewStocktake extends ViewRecord
{
    protected static string $resource = StocktakeResource::class;

    public function getTitle(): string
    {
        return __('stocktake.label') . ' ' . $this->getRecord()->code;
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(4)
                ->schema([
                    TextEntry::make('warehouse.name')->label(__('stocktake.warehouse')),
                    TextEntry::make('status')
                        ->label(__('stocktake.status'))
                        ->badge()
                        ->formatStateUsing(fn (string $state) => __("stocktake.statuses.{$state}")),
                    TextEntry::make('startedBy.name')->label(__('stocktake.started_by'))->placeholder('—'),
                    TextEntry::make('started_at')
                        ->label(__('stocktake.started_at'))
                        ->formatStateUsing(fn ($state) => Jalali::formatDateTime($state))
                        ->placeholder('—'),
                    TextEntry::make('applied_at')
                        ->label(__('stocktake.applied_at'))
                        ->badge()
                        ->state(fn (Stocktake $r) => $r->isApplied()
                            ? Jalali::formatDateTime($r->applied_at)
                            : __('stocktake.applied_never'))
                        ->color(fn (Stocktake $r) => $r->isApplied() ? 'success' : 'gray'),
                    TextEntry::make('notes')->label(__('stocktake.notes'))->placeholder('—')->columnSpanFull(),
                ]),

            Section::make(__('stocktake.summary'))
                ->columns(4)
                ->schema([
                    TextEntry::make('total_lines')
                        ->label(__('stocktake.total_lines'))
                        ->state(fn (Stocktake $r) => Jalali::quantity($r->lines->count()))
                        ->weight('bold')->size('lg'),

                    TextEntry::make('counted_lines')
                        ->label(__('stocktake.counted_lines'))
                        ->state(fn (Stocktake $r) => Jalali::quantity($r->countedLines()->count()))
                        ->weight('bold')->size('lg'),

                    TextEntry::make('discrepancy_lines')
                        ->label(__('stocktake.discrepancy_lines'))
                        ->state(fn (Stocktake $r) => Jalali::quantity($r->discrepancies()->count()))
                        ->weight('bold')->size('lg')
                        ->color(fn (Stocktake $r) => $r->discrepancies()->count() > 0 ? 'danger' : 'success'),

                    TextEntry::make('totals')
                        ->label(__('stocktake.total_surplus') . ' / ' . __('stocktake.total_shortage'))
                        ->state(fn (Stocktake $r) => Jalali::quantity($r->totalSurplus()) . ' / ' . Jalali::quantity($r->totalShortage()))
                        ->weight('bold')->size('lg'),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sheet')
                ->label(__('stocktake.sheet_download'))
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->url(fn () => route('stocktake.sheet', ['stocktake' => $this->getRecord()->id]))
                ->openUrlInNewTab(),

            Action::make('report')
                ->label(__('stocktake.report'))
                ->icon(Heroicon::OutlinedDocumentText)
                ->color('gray')
                ->url(fn () => route('stocktake.report', ['stocktake' => $this->getRecord()->id]))
                ->openUrlInNewTab(),

            // پایان انبارگردانی — فقط شمارش را می‌بندد؛ موجودی را دست نمی‌زند.
            Action::make('finish')
                ->label(__('stocktake.close'))
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('warning')
                ->modalHeading(__('stocktake.close_heading'))
                ->visible(fn () => $this->getRecord()->isEditable()
                    && (auth()->user()?->can(Permission::ManageStocktakes->value) ?? false))
                ->schema([
                    \Filament\Forms\Components\Placeholder::make('warning')
                        ->hiddenLabel()
                        ->content(fn () => new HtmlString(
                            '<div class="text-warning-600 dark:text-warning-400 font-medium leading-relaxed">'
                            . e(__('stocktake.close_warning'))
                            . $this->uncountedWarning()
                            . '</div>',
                        )),

                    Checkbox::make('understood')->label(__('stocktake.close_confirm'))->accepted()->required(),
                ])
                ->action(function (StocktakeService $service) {
                    $service->finish($this->getRecord());

                    Notification::make()->success()->title(__('stocktake.closed'))->send();

                    $this->refreshFormData([]);
                }),

            // به‌روزرسانی انبار — اعمال مغایرت‌ها روی موجودی؛ اختیاری و یک‌بار.
            Action::make('applyToStock')
                ->label(__('stocktake.apply'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('danger')
                ->modalHeading(__('stocktake.apply_heading'))
                ->modalSubmitActionLabel(__('stocktake.apply_button'))
                ->visible(fn () => $this->getRecord()->canApplyToStock()
                    && (auth()->user()?->can(Permission::ManageStocktakes->value) ?? false))
                ->schema([
                    \Filament\Forms\Components\Placeholder::make('warning')
                        ->hiddenLabel()
                        ->content(fn () => new HtmlString(
                            '<div class="text-danger-600 dark:text-danger-400 font-medium leading-relaxed">'
                            . e(__('stocktake.apply_warning', [
                                'count' => Jalali::quantity($this->getRecord()->discrepancies()->count()),
                            ]))
                            . '</div>',
                        )),

                    Checkbox::make('understood')->label(__('stocktake.apply_confirm'))->accepted()->required(),
                ])
                ->action(function (StocktakeService $service) {
                    $result = $service->applyToStock($this->getRecord());

                    Notification::make()
                        ->success()
                        ->title(__('stocktake.applied', ['adjusted' => Jalali::quantity($result['adjusted'])]))
                        ->send();

                    $this->refreshFormData([]);
                }),

            // لغو انبارگردانی — فقط تا وقتی هنوز بسته نشده است.
            Action::make('cancel')
                ->label(__('stocktake.cancel'))
                ->icon(Heroicon::OutlinedXCircle)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading(__('stocktake.cancel_heading'))
                ->modalDescription(__('stocktake.cancel_warning'))
                ->visible(fn () => $this->getRecord()->isEditable()
                    && (auth()->user()?->can(Permission::ManageStocktakes->value) ?? false))
                ->action(function (StocktakeService $service) {
                    $service->cancel($this->getRecord());

                    Notification::make()->success()->title(__('stocktake.cancelled'))->send();

                    $this->refreshFormData([]);
                }),
        ];
    }

    /** اگر سطری شمرده نشده، پیش از ثبت نهایی هشدار بدهیم. */
    private function uncountedWarning(): string
    {
        /** @var Stocktake $record */
        $record = $this->getRecord();
        $uncounted = $record->lines->count() - $record->countedLines()->count();

        if ($uncounted === 0) {
            return '';
        }

        return '<br><br>' . e(__('stocktake.close_uncounted', ['count' => Jalali::quantity($uncounted)]));
    }
}
