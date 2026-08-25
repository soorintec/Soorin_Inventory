<?php

namespace App\Filament\Resources\CustomerSystems\Schemas;

use App\Models\Customer;
use App\Models\Project;
use App\Models\SystemVersion;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class CustomerSystemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->label(__('systems.cs_code'))->required()->maxLength(30)->unique(ignoreRecord: true),
            TextInput::make('name')->label(__('systems.cs_name'))->required()->maxLength(255),

            Select::make('customer_id')
                ->label(__('systems.customer'))
                ->options(fn () => Customer::pluck('name', 'id'))
                ->searchable()
                ->required(),

            Select::make('project_id')
                ->label(__('systems.project_label'))
                ->options(fn () => Project::pluck('title', 'id'))
                ->searchable(),

            Select::make('system_version_id')
                ->label(__('systems.system_version'))
                ->options(fn () => SystemVersion::with('systemModel')->get()->mapWithKeys(fn (SystemVersion $v) => [$v->id => $v->displayName()]))
                ->searchable(),

            TextInput::make('location')->label(__('systems.location'))->maxLength(255),
            DatePicker::make('installed_at')->label(__('systems.installed_at')),
            DatePicker::make('warranty_until')->label(__('systems.warranty_until')),

            Select::make('status')
                ->label(__('systems.status'))
                ->options(__('systems.cs_status'))
                ->default('active')
                ->required()
                ->native(false),

            Textarea::make('notes')->label(__('systems.notes'))->rows(2)->columnSpanFull(),
        ])->columns(2);
    }
}
