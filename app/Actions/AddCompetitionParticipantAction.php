<?php

namespace App\Actions;

use App\Enums\ParticipantStatus;
use App\Enums\ParticipantType;
use App\Models\Competition;
use App\Models\CompetitionParticipant;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class AddCompetitionParticipantAction
{
    /**
     * Register a team or user as a participant in a competition.
     *
     * Seed is auto-assigned based on existing participant count if not provided.
     */
    public function execute(
        Competition $competition,
        Model $participant,
        ?int $seed = null,
    ): CompetitionParticipant {
        $participantType = match (true) {
            $participant instanceof Team => ParticipantType::Team,
            $participant instanceof User => ParticipantType::User,
            default => throw new InvalidArgumentException(
                'Participant must be a Team or User instance.'
            ),
        };

        $alreadyRegistered = $competition->participants()
            ->where('participant_type', $participantType)
            ->where('participant_id', $participant->id)
            ->whereNotIn('status', [ParticipantStatus::Withdrawn->value, ParticipantStatus::Disqualified->value])
            ->exists();

        if ($alreadyRegistered) {
            throw new InvalidArgumentException(
                "Participant [{$participant->id}] is already registered in competition [{$competition->id}]."
            );
        }

        if ($seed === null) {
            $seed = $competition->participants()->max('seed') + 1;
        }

        return CompetitionParticipant::create([
            'competition_id' => $competition->id,
            'participant_type' => $participantType,
            'participant_id' => $participant->id,
            'seed' => $seed,
            'status' => ParticipantStatus::Registered,
        ]);
    }
}
