<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\User;
use App\Policies\Concerns\ChecksProjectRole;

/** The activity log is read-only for everyone. */
class ActivityPolicy
{
    use ChecksProjectRole;

    public function viewAny(User $user): bool
    {
        return $this->isMember($user);
    }

    public function view(User $user, Activity $activity): bool
    {
        return $this->isMember($user, $activity);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Activity $activity): bool
    {
        return false;
    }

    public function delete(User $user, Activity $activity): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
