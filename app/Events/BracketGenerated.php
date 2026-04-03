<?php

namespace App\Events;

use App\Models\CompetitionStage;

class BracketGenerated
{
    public function __construct(
        public CompetitionStage $stage,
    ) {}
}
