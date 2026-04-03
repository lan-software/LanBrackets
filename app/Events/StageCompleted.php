<?php

namespace App\Events;

use App\Models\CompetitionStage;

class StageCompleted
{
    public function __construct(
        public CompetitionStage $stage,
    ) {}
}
