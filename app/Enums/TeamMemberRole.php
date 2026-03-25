<?php

namespace App\Enums;

enum TeamMemberRole: string
{
    case Player = 'player';
    case Coach = 'coach';
    case Manager = 'manager';
    case Substitute = 'substitute';
}
