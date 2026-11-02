<?php

namespace App\Policies;

use App\Models\Invitation;
use App\Models\User;
use App\Policies\Concerns\ChecksProjectRole;

class InvitationPolicy
{
    use ChecksProjectRole;

    public function viewAny(User $user): bool
    {
        return $this->isOwner($user);
    }

    public function create(User $user): bool
    {
        return $this->isOwner($user);
    }

    public function delete(User $user, Invitation $invitation): bool
    {
        return $this->isOwner($user, $invitation);
    }
}
