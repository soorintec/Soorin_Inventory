<?php

namespace App\Filament\Resources\SystemVersions\Pages;

use App\Filament\Resources\SystemModels\SystemModelResource;
use App\Filament\Resources\SystemVersions\SystemVersionResource;
use App\Models\SystemVersion;
use App\Support\Jalali;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * صفحه کامل یک نسخه سامانه: مشخصات نسخه بالا، لیست قطعات پایین.
 *
 * سه عدد بالای صفحه جواب سؤال‌هایی است که مالک پروژه واقعاً می‌پرسد:
 * این دستگاه چقدر تمام می‌شود، چند تا از آن می‌توانم همین حالا بسازم،
 * و کدام قطعه‌ها کم است.
 */
class ViewSystemVersion extends ViewRecord
{
    protected static string $resource = SystemVersionResource::class;

    public function getTitle(): string
    {
        /** @var SystemVersion $record */
        $record = $this->getRecord();

        return $record->displayName();
    }

    /**
     * نان‌ریزه‌ها باید به همان مدلی برگردند که کاربر از آن آمده، نه فقط به
     * فهرست کلی مدل‌ها.
     *
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        /** @var SystemVersion $record */
        $record = $this->getRecord();

        return [
            SystemModelResource::getUrl('index') => __('systems.model_plural'),
            SystemModelResource::getUrl('view', ['record' => $record->system_model_id]) => $record->systemModel->name,
            '' => $record->version_code,
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(4)
                ->schema([
                    TextEntry::make('systemModel.name')->label(__('systems.model_label')),
                    TextEntry::make('version_code')->label(__('systems.version_code')),
                    TextEntry::make('year')
                        ->label(__('systems.year'))
                        ->placeholder('—')
                        ->formatStateUsing(fn ($state) => $state ? Jalali::digits((string) $state) : null),
                    TextEntry::make('is_active')
                        ->label(__('common.active'))
                        ->badge()
                        ->formatStateUsing(fn ($state) => $state ? __('common.yes') : __('common.no'))
                        ->color(fn ($state) => $state ? 'success' : 'gray'),
                    TextEntry::make('notes')->label(__('systems.notes'))->placeholder('—')->columnSpanFull(),
                ]),

            Section::make(__('systems.bom_summary'))
                ->columns(3)
                ->schema([
                    TextEntry::make('estimated_cost')
                        ->label(__('systems.bom_estimated_cost'))
                        ->helperText(__('systems.bom_estimated_cost_hint'))
                        ->state(fn (SystemVersion $record) => Jalali::money($record->estimatedCost()) . ' ' . __('common.rial'))
                        ->weight('bold')
                        ->size('lg'),

                    TextEntry::make('buildable')
                        ->label(__('systems.bom_buildable'))
                        ->helperText(__('systems.bom_buildable_hint'))
                        ->state(fn (SystemVersion $record) => Jalali::quantity($record->buildableUnits()))
                        ->weight('bold')
                        ->size('lg')
                        ->color(fn (SystemVersion $record) => $record->buildableUnits() > 0 ? 'success' : 'danger'),

                    TextEntry::make('shortage_count')
                        ->label(__('systems.bom_shortage_count'))
                        ->state(fn (SystemVersion $record) => Jalali::quantity($record->shortageCount()))
                        ->weight('bold')
                        ->size('lg')
                        ->color(fn (SystemVersion $record) => $record->shortageCount() > 0 ? 'danger' : 'success'),
                ]),
        ]);
    }
}
