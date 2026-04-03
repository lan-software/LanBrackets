<?php

namespace App\Events;

use App\Models\CompetitionMatch;

class MatchResultReported
{
    public function __construct(
        public CompetitionMatch $match,
    ) {}
}
