<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Models\Customer;
use App\Models\SystemVersion;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->label(__('systems.code'))->required()->maxLength(30)->unique(ignoreRecord: true),
            TextInput::make('title')->label(__('systems.title'))->required()->maxLength(255),

            Select::make('customer_id')
                ->label(__('systems.customer'))
                ->options(fn () => Customer::pluck('name', 'id'))
                ->searchable()
                ->required(),

            Select::make('system_version_id')
                ->label(__('systems.system_version'))
                ->options(fn () => SystemVersion::with('systemModel')->get()->mapWithKeys(fn (SystemVersion $v) => [$v->id => $v->displayName()]))
                ->searchable()
                ->helperText(__('systems.no_system_version')),

            DatePicker::make('start_date')->label(__('systems.start_date')),
            DatePicker::make('delivery_date')->label(__('systems.delivery_date')),

            Select::make('status')
                ->label(__('systems.status'))
                ->options(__('systems.statuses'))
                ->default('draft')
                ->required()
                ->native(false),

            TextInput::make('sale_price')->label(__('systems.sale_price'))->numeric()->default(0)->suffix(__('common.currency')),

            Textarea::make('notes')->label(__('systems.notes'))->rows(2)->columnSpanFull(),
        ])->columns(2);
    }
}
