<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Policies\Concerns\ChecksProjectRole;

class ProjectPolicy
{
    use ChecksProjectRole;

    public function view(User $user, Project $project): bool
    {
        return $this->isMember($user, $project);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Project $project): bool
    {
        return $this->isOwner($user, $project);
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->isOwner($user, $project);
    }
}
