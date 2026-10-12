<?php

namespace App\Policies;

use App\Models\User;

class ContentPolicy extends RolePolicy
{
    protected function allows(User $user): bool
    {
        return $user->canManageContent();
    }
}
