<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Services\InventoryReportService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Hekmatinasser\Verta\Verta;

class Reports extends Page
{
    protected string $view = 'filament.pages.reports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = 92;

    public ?array $data = [];

    public array $report = [];

    public static function getNavigationLabel(): string
    {
        return __('reports.label');
    }

    public function getTitle(): string
    {
        return __('reports.label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('reports.nav_group');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->can(Permission::ViewReports->value) ?? false;
    }

    public function mount(): void
    {
        abort_unless(auth()->user()?->can(Permission::ViewReports->value), 403);

        $this->form->fill(['preset' => 'this_year']);
        $this->applyPreset();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make()
                    ->columns(3)
                    ->schema([
                        Select::make('preset')
                            ->label(__('common.select'))
                            ->options([
                                'this_month' => __('reports.range_this_month'),
                                'this_year'  => __('reports.range_this_year'),
                                'custom'     => __('reports.range_custom'),
                            ])
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(fn () => $this->applyPreset()),

                        DatePicker::make('from')->label(__('reports.from_date'))
                            ->visible(fn ($get) => $get('preset') === 'custom')->live(),
                        DatePicker::make('to')->label(__('reports.to_date'))
                            ->visible(fn ($get) => $get('preset') === 'custom')->live(),
                    ]),
            ]);
    }

    public function applyPreset(): void
    {
        $preset = $this->data['preset'] ?? 'this_year';
        $now = Verta::now();
        $y = (int) $now->format('Y');
        $m = (int) $now->format('m');

        [$from, $to] = match ($preset) {
            'this_month' => [
                Carbon::instance(Verta::createJalali($y, $m, 1, 0, 0, 0)->datetime()),
                Carbon::today()->endOfDay(),
            ],
            'custom' => [
                filled($this->data['from'] ?? null) ? Carbon::parse($this->data['from']) : Carbon::today()->startOfMonth(),
                filled($this->data['to'] ?? null) ? Carbon::parse($this->data['to'])->endOfDay() : Carbon::today()->endOfDay(),
            ],
            default => [   // this_year
                Carbon::instance(Verta::createJalali($y, 1, 1, 0, 0, 0)->datetime()),
                Carbon::today()->endOfDay(),
            ],
        };

        if ($preset !== 'custom') {
            $this->data['from'] = $from->toDateString();
            $this->data['to']   = $to->toDateString();
        }

        $this->report = app(InventoryReportService::class)->generate($from, $to);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')
                ->label(__('reports.export_excel'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(fn () => route('reports.export.excel', ['from' => $this->data['from'], 'to' => $this->data['to']]))
                ->openUrlInNewTab(),

            Action::make('printReport')
                ->label(__('reports.print'))
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn () => route('reports.export.pdf', ['from' => $this->data['from'], 'to' => $this->data['to']]))
                ->openUrlInNewTab(),
        ];
    }
}
