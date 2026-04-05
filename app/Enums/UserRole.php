<?php

namespace App\Enums;

enum UserRole: string
{
    case User = 'user';
    case Moderator = 'moderator';
    case Admin = 'admin';
    case Superadmin = 'superadmin';

    public static function privileged(): array
    {
        return [
            self::Moderator,
            self::Admin,
            self::Superadmin,
        ];
    }
}
