<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Project;
use App\Services\ProjectChecklistService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditProject extends EditRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        /** @var Project $project */
        $project = $this->getRecord();

        return [
            Action::make('generateChecklist')
                ->label(__('systems.generate_checklist'))
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn () => $project->system_version_id !== null)
                ->action(function () use ($project) {
                    $this->save(shouldRedirect: false);

                    app(ProjectChecklistService::class)->generateFromBom($project->fresh());

                    Notification::make()->success()->title(__('common.saved'))->send();

                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $project]));
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
