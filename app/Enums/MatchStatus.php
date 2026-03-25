<?php

namespace App\Enums;

enum MatchStatus: string
{
    case Pending = 'pending';
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Finished = 'finished';
    case Cancelled = 'cancelled';
}
