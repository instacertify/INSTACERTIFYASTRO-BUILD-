<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use Audited, HasApiTokens, HasFactory, Notifiable;

    public const ROLES = [
        'admin' => 'Admin — everything incl. users & settings',
        'editor' => 'Editor — website content',
        'sales' => 'Sales — leads & subscribers',
        'hr' => 'HR — vacancies & all job applications (CVs)',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::updated(function (User $user) {
            if ($user->wasChanged('password')) {
                activity()->performedOn($user)->event('password_changed')->log('Changed password');
            }
        });
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function canManageContent(): bool
    {
        return in_array($this->role, ['admin', 'editor'], true);
    }

    public function canManageLeads(): bool
    {
        return in_array($this->role, ['admin', 'sales'], true);
    }

    public function canManageCareers(): bool
    {
        return in_array($this->role, ['admin', 'hr'], true);
    }

    /** Any role can be added to a vacancy's hiring team to review just that vacancy's applicants. */
    public function isOnAHiringTeam(): bool
    {
        return $this->vacancies()->exists();
    }

    public function vacancies(): BelongsToMany
    {
        return $this->belongsToMany(Vacancy::class, 'vacancy_user');
    }
}
