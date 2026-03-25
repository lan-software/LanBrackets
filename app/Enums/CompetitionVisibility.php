<?php

namespace App\Enums;

enum CompetitionVisibility: string
{
    case Private = 'private';
    case Unlisted = 'unlisted';
    case Public = 'public';
}
