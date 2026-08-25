<?php

namespace App\Observers;

use App\Models\Project;
use App\Services\ProjectChecklistService;

/**
 * آزادسازی رزرو موجودی هنگام حذف یا لغو پروژه.
 * بدون این، موجودی رزروشده یک پروژه لغوشده برای همیشه قفل می‌ماند و
 * پروژه‌های بعدی آن را «کسری» می‌بینند.
 */
class ProjectObserver
{
    public function __construct(private readonly ProjectChecklistService $checklist) {}

    public function updated(Project $project): void
    {
        if ($project->wasChanged('status') && $project->status === Project::STATUS_CANCELLED) {
            $this->checklist->releaseReservations($project);
        }
    }

    public function deleting(Project $project): void
    {
        $this->checklist->releaseReservations($project);
    }
}
