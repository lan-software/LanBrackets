<?php

namespace App\Enums;

enum StageStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
}
