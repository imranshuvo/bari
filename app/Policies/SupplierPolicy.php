<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;
use App\Policies\Concerns\ChecksProjectRole;

class SupplierPolicy
{
    use ChecksProjectRole;

    public function viewAny(User $user): bool
    {
        return $this->isMember($user);
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $this->isMember($user, $supplier);
    }

    public function create(User $user): bool
    {
        return $this->canEdit($user);
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $this->canEdit($user, $supplier);
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $this->isOwner($user, $supplier);
    }

    public function deleteAny(User $user): bool
    {
        return $this->isOwner($user);
    }
}
