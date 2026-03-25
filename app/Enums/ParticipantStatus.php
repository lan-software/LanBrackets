<?php

namespace App\Enums;

enum ParticipantStatus: string
{
    case Registered = 'registered';
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case Disqualified = 'disqualified';
    case Withdrawn = 'withdrawn';
}
