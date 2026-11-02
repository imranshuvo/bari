<?php

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Models\Membership;
use App\Models\User;
use App\Policies\Concerns\ChecksProjectRole;

class MembershipPolicy
{
    use ChecksProjectRole;

    public function viewAny(User $user): bool
    {
        return $this->isMember($user);
    }

    public function view(User $user, Membership $membership): bool
    {
        return $this->isMember($user, $membership);
    }

    public function create(User $user): bool
    {
        return $this->isOwner($user);
    }

    /** A project must always keep at least one owner. */
    public function update(User $user, Membership $membership): bool
    {
        return $this->isOwner($user, $membership) && ! $this->isLastOwner($membership);
    }

    public function delete(User $user, Membership $membership): bool
    {
        return $this->isOwner($user, $membership) && ! $this->isLastOwner($membership);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    private function isLastOwner(Membership $membership): bool
    {
        return $membership->role === ProjectRole::Owner && $membership->project->ownerCount() <= 1;
    }
}
