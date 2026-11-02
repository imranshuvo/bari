<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;
use App\Policies\Concerns\ChecksProjectRole;

/** The budget belongs to the owners; everyone else can look. */
class CategoryPolicy
{
    use ChecksProjectRole;

    public function viewAny(User $user): bool
    {
        return $this->isMember($user);
    }

    public function view(User $user, Category $category): bool
    {
        return $this->isMember($user, $category);
    }

    public function create(User $user): bool
    {
        return $this->isOwner($user);
    }

    public function update(User $user, Category $category): bool
    {
        return $this->isOwner($user, $category);
    }

    public function delete(User $user, Category $category): bool
    {
        return $this->isOwner($user, $category);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return $this->isOwner($user);
    }
}
