<?php

namespace App\Actions;

use App\Models\TeamMember;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class RemoveTeamMemberAction
{
    /**
     * Remove a member from a team by setting their left_at timestamp.
     * Prevents removing the last captain without reassigning first.
     */
    public function execute(TeamMember $member): void
    {
        if (! $member->isActive()) {
            throw new InvalidArgumentException(
                "Member [{$member->id}] is already inactive."
            );
        }

        if ($member->is_captain) {
            $otherCaptains = $member->team->captains()
                ->where('id', '!=', $member->id)
                ->count();

            if ($otherCaptains === 0) {
                $nextCandidate = $member->team->activeMembers()
                    ->where('id', '!=', $member->id)
                    ->oldest('joined_at')
                    ->first();

                if ($nextCandidate === null) {
                    throw new InvalidArgumentException(
                        'Cannot remove the last member of the team.'
                    );
                }

                throw new InvalidArgumentException(
                    'Cannot remove the last captain. Assign a new captain first.'
                );
            }
        }

        $member->update(['left_at' => Carbon::now()]);
    }
}
