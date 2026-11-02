<?php

namespace App\Policies;

use App\Models\Agreement;
use App\Models\User;
use App\Policies\Concerns\ChecksProjectRole;

class AgreementPolicy
{
    use ChecksProjectRole;

    public function viewAny(User $user): bool
    {
        return $this->isMember($user);
    }

    public function view(User $user, Agreement $agreement): bool
    {
        return $this->isMember($user, $agreement);
    }

    public function create(User $user): bool
    {
        return $this->canEdit($user);
    }

    public function update(User $user, Agreement $agreement): bool
    {
        return $this->canEdit($user, $agreement);
    }

    public function delete(User $user, Agreement $agreement): bool
    {
        return $this->isOwner($user, $agreement);
    }

    public function deleteAny(User $user): bool
    {
        return $this->isOwner($user);
    }
}
