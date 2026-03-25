<?php

namespace App\Actions;

use App\Enums\TeamMemberRole;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class AddTeamMemberAction
{
    /**
     * Add a user to a team. If the team has no active members, the new member
     * automatically becomes captain to enforce the captain constraint.
     */
    public function execute(
        Team $team,
        User $user,
        ?TeamMemberRole $role = null,
        bool $isCaptain = false,
    ): TeamMember {
        $existingActive = $team->activeMembers()
            ->where('user_id', $user->id)
            ->exists();

        if ($existingActive) {
            throw new InvalidArgumentException(
                "User [{$user->id}] is already an active member of team [{$team->id}]."
            );
        }

        $hasActiveCaptain = $team->captains()->exists();

        return TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'role' => $role,
            'is_captain' => $isCaptain || ! $hasActiveCaptain,
            'joined_at' => Carbon::now(),
        ]);
    }
}
