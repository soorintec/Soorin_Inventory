<?php

namespace App\Filament\Resources\Projects;

use App\Enums\Permission;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Filament\Resources\Projects\RelationManagers\ChecklistRelationManager;
use App\Filament\Resources\Projects\Schemas\ProjectForm;
use App\Filament\Resources\Projects\Tables\ProjectsTable;
use App\Models\Project;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 31;

    public static function getModelLabel(): string
    {
        return __('systems.project_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('systems.project_plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('systems.nav_group');
    }

    public static function form(Schema $schema): Schema
    {
        return ProjectForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProjectsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [ChecklistRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListProjects::route('/'),
            'create' => CreateProject::route('/create'),
            'edit'   => EditProject::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permission::ViewProjects->value) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can(Permission::ManageProjects->value) ?? false;
    }

    public static function canEdit(mixed $record): bool
    {
        return auth()->user()?->can(Permission::ManageProjects->value) ?? false;
    }

    public static function canDelete(mixed $record): bool
    {
        return auth()->user()?->can(Permission::ManageProjects->value) ?? false;
    }
}
