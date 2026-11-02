<?php

namespace App\Policies\Concerns;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

trait ChecksProjectRole
{
    /**
     * @param  Project|Model|null  $subject  a project, a model that belongs to one, or null for the current project
     */
    protected function role(User $user, Project|Model|null $subject = null): ?ProjectRole
    {
        $project = $this->projectFor($subject);

        return $project instanceof Project ? $project->roleOf($user) : null;
    }

    /** Reuses the current project (and its cached roles) whenever possible, so lists don't run a query per row. */
    private function projectFor(Project|Model|null $subject): ?Project
    {
        $tenant = Filament::getTenant();

        if ($subject === null || $subject instanceof Project) {
            return $subject ?? ($tenant instanceof Project ? $tenant : null);
        }

        if ($tenant instanceof Project && $tenant->getKey() === $subject->project_id) {
            return $tenant;
        }

        return $subject->project;
    }

    protected function isMember(User $user, Project|Model|null $project = null): bool
    {
        return $this->role($user, $project) !== null;
    }

    protected function canEdit(User $user, Project|Model|null $project = null): bool
    {
        return (bool) $this->role($user, $project)?->canEdit();
    }

    protected function isOwner(User $user, Project|Model|null $project = null): bool
    {
        return (bool) $this->role($user, $project)?->isOwner();
    }
}
