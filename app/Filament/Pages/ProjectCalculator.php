<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Models\SystemModel;
use App\Models\SystemVersion;
use App\Services\ProjectCalculatorService;
use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * ماشین‌حساب پروژه — انتخاب چند مدل سامانه با تعداد، و گزارش قطعات موردنیاز،
 * قیمت کل به تفکیک ارز، و وضعیت موجودی هر قطعه.
 */
class ProjectCalculator extends Page
{
    protected string $view = 'filament.pages.project-calculator';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static ?int $navigationSort = 35;

    public ?array $data = [];

    public array $result = [];

    public static function getNavigationLabel(): string
    {
        return __('calculator.label');
    }

    public function getTitle(): string
    {
        return __('calculator.label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('systems.nav_group');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::ViewProjects->value) ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill(['rows' => [['quantity' => 1]]]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Repeater::make('rows')
                    ->label(__('calculator.selections'))
                    ->addActionLabel(__('calculator.add_system'))
                    ->columns(3)
                    ->minItems(1)
                    ->schema([
                        // مرحلهٔ اول: انتخاب مدل سامانه. با تغییرِ مدل، نسخهٔ
                        // انتخاب‌شدهٔ قبلی پاک می‌شود تا نسخهٔ مدلِ دیگری باقی نماند.
                        Select::make('system_model_id')
                            ->label(__('calculator.system'))
                            ->options(fn () => SystemModel::orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn (callable $set) => $set('system_version_id', null))
                            ->required(),

                        // مرحلهٔ دوم: نسخه‌های همان مدل. تا وقتی مدلی انتخاب نشده،
                        // فهرست خالی است و راهنما نمایش داده می‌شود.
                        Select::make('system_version_id')
                            ->label(__('calculator.version'))
                            ->options(fn (callable $get) => filled($get('system_model_id'))
                                ? SystemVersion::where('system_model_id', $get('system_model_id'))
                                    ->orderByDesc('version_code')
                                    ->get()
                                    ->mapWithKeys(fn (SystemVersion $v) => [$v->id => $v->version_code])
                                : [])
                            ->placeholder(__('calculator.version_placeholder'))
                            ->searchable()
                            ->required(),

                        TextInput::make('quantity')
                            ->label(__('calculator.count'))
                            ->numeric()
                            ->minValue(1)
                            ->step(1)
                            ->default(1)
                            ->required(),
                    ]),
            ]);
    }

    public function calculate(): void
    {
        $state = $this->form->getState();

        $this->result = app(ProjectCalculatorService::class)->calculate($state['rows'] ?? []);
    }
}
