<?php

namespace App\Events;

use App\Models\Competition;

class CompetitionCompleted
{
    public function __construct(
        public Competition $competition,
    ) {}
}
