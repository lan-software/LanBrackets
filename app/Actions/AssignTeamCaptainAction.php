<?php

namespace App\Actions;

use App\Models\Team;
use App\Models\TeamMember;
use InvalidArgumentException;

class AssignTeamCaptainAction
{
    /**
     * Assign a team member as captain. Optionally removes captain from the
     * current captain(s) to enforce exactly-one-captain constraint.
     */
    public function execute(TeamMember $newCaptain, bool $removePreviousCaptains = true): void
    {
        if (! $newCaptain->isActive()) {
            throw new InvalidArgumentException(
                "Cannot assign inactive member [{$newCaptain->id}] as captain."
            );
        }

        if ($newCaptain->is_captain) {
            return;
        }

        /** @var Team $team */
        $team = $newCaptain->team;

        if ($removePreviousCaptains) {
            $team->captains()->update(['is_captain' => false]);
        }

        $newCaptain->update(['is_captain' => true]);
    }
}
