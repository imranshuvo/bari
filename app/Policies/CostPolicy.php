<?php

namespace App\Policies;

use App\Models\Cost;
use App\Models\User;
use App\Policies\Concerns\ChecksProjectRole;

class CostPolicy
{
    use ChecksProjectRole;

    public function viewAny(User $user): bool
    {
        return $this->isMember($user);
    }

    public function view(User $user, Cost $cost): bool
    {
        return $this->isMember($user, $cost);
    }

    public function create(User $user): bool
    {
        return $this->canEdit($user);
    }

    public function update(User $user, Cost $cost): bool
    {
        return $this->canEdit($user, $cost);
    }

    /** Owners can delete anything; editors only what they added themselves. */
    public function delete(User $user, Cost $cost): bool
    {
        return $this->isOwner($user, $cost)
            || ($this->canEdit($user, $cost) && $cost->created_by === $user->id);
    }

    public function deleteAny(User $user): bool
    {
        return $this->isOwner($user);
    }
}
