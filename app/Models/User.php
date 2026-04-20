<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'lancore_user_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function hasRole(UserRole|string $role): bool
    {
        $role = is_string($role) ? UserRole::from($role) : $role;

        return $this->role === $role;
    }

    /**
     * @param  array<int, UserRole|string>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    /** @return HasMany<TeamMember, $this> */
    public function teamMemberships(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    /** @return HasMany<CompetitionParticipant, $this> */
    public function competitionParticipants(): HasMany
    {
        return $this->hasMany(CompetitionParticipant::class, 'participant_id')
            ->where('participant_type', 'user');
    }
}
