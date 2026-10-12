<?php

namespace App\Policies;

use App\Models\User;

class VacancyPolicy extends RolePolicy
{
    protected function allows(User $user): bool
    {
        return $user->canManageCareers();
    }
}
