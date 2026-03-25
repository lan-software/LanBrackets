<?php

namespace App\Enums;

enum CompetitionStatus: string
{
    case Draft = 'draft';
    case Planned = 'planned';
    case RegistrationOpen = 'registration_open';
    case RegistrationClosed = 'registration_closed';
    case Running = 'running';
    case Paused = 'paused';
    case Finished = 'finished';
    case Archived = 'archived';

    /** Statuses in which participants can still register. */
    public function isRegistrationPhase(): bool
    {
        return $this === self::RegistrationOpen;
    }

    /** Statuses in which matches may be played. */
    public function isActive(): bool
    {
        return in_array($this, [self::Running, self::Paused]);
    }
}
