<?php

namespace App\Enums;

enum CompetitionType: string
{
    case Tournament = 'tournament';
    case League = 'league';
    case Race = 'race';
}
