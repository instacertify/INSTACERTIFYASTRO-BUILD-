<?php

namespace App\Policies;

use App\Models\User;

/**
 * Grants every Filament ability based on the user's role, so resources are protected
 * on direct URL access, not just hidden from navigation. Filament requires the
 * methods to exist explicitly (it checks method_exists, not __call).
 */
abstract class RolePolicy
{
    abstract protected function allows(User $user): bool;

    public function viewAny(User $user): bool
    {
        return $this->allows($user);
    }

    public function view(User $user): bool
    {
        return $this->allows($user);
    }

    public function create(User $user): bool
    {
        return $this->allows($user);
    }

    public function update(User $user): bool
    {
        return $this->allows($user);
    }

    public function delete(User $user): bool
    {
        return $this->allows($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->allows($user);
    }

    public function replicate(User $user): bool
    {
        return $this->allows($user);
    }

    public function reorder(User $user): bool
    {
        return $this->allows($user);
    }

    public function restore(User $user): bool
    {
        return $this->allows($user);
    }

    public function restoreAny(User $user): bool
    {
        return $this->allows($user);
    }

    public function forceDelete(User $user): bool
    {
        return $this->allows($user);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->allows($user);
    }
}
