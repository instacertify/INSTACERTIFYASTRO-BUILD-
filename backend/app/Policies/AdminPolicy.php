<?php

namespace App\Policies;

use App\Models\User;

class AdminPolicy extends RolePolicy
{
    protected function allows(User $user): bool
    {
        return $user->isAdmin();
    }
}
