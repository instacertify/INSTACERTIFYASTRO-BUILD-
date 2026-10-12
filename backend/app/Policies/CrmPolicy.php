<?php

namespace App\Policies;

use App\Models\User;

class CrmPolicy extends RolePolicy
{
    protected function allows(User $user): bool
    {
        return $user->canManageLeads();
    }
}
