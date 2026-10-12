<?php

namespace App\Policies;

use App\Models\JobApplication;
use App\Models\User;

/**
 * HR and admins see every application. Anyone else sees only applications for vacancies
 * whose hiring team they are on, and can move them through the pipeline but not delete them.
 */
class JobApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageCareers() || $user->isOnAHiringTeam();
    }

    public function view(User $user, JobApplication $application): bool
    {
        return $user->canManageCareers()
            || ($application->vacancy?->hiringTeam()->whereKey($user->getKey())->exists() ?? false);
    }

    public function update(User $user, JobApplication $application): bool
    {
        return $this->view($user, $application);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, JobApplication $application): bool
    {
        return $user->canManageCareers();
    }

    public function deleteAny(User $user): bool
    {
        return $user->canManageCareers();
    }

    public function forceDelete(User $user, JobApplication $application): bool
    {
        return $user->canManageCareers();
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->canManageCareers();
    }

    public function restore(User $user, JobApplication $application): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, JobApplication $application): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }
}
