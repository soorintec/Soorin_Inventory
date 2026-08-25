<?php

namespace App\Filament\Resources\SystemModels\Pages;

use App\Filament\Resources\SystemModels\SystemModelResource;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewSystemModel extends ViewRecord
{
    protected static string $resource = SystemModelResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(3)
                ->schema([
                    TextEntry::make('code')->label(__('systems.model_code')),
                    TextEntry::make('name')->label(__('systems.model_name')),
                    TextEntry::make('description')->label(__('systems.description'))->placeholder('—')->columnSpanFull(),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
