<?php

namespace App\Enums;

use App\Models\Team;
use App\Models\User;

enum ParticipantType: string
{
    case Team = 'team';
    case User = 'user';

    /** Resolve the Eloquent model class for this participant type. */
    public function modelClass(): string
    {
        return match ($this) {
            self::Team => Team::class,
            self::User => User::class,
        };
    }
}
