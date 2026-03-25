<?php

namespace App\Enums;

enum MatchResult: string
{
    case Win = 'win';
    case Loss = 'loss';
    case Draw = 'draw';
    case Bye = 'bye';
    case Forfeit = 'forfeit';
}
