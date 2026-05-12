<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompetitionMatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'round_number' => $this->round_number,
            'sequence' => $this->sequence,
            'status' => $this->status->value,
            'scheduled_at' => $this->scheduled_at,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
            'winner_participant_id' => $this->winner_participant_id,
            'loser_participant_id' => $this->loser_participant_id,
            'settings' => $this->settings,
            'participants' => $this->matchParticipants->map(fn ($mp) => [
                'competition_participant_id' => $mp->competition_participant_id,
                'slot' => $mp->slot,
                'score' => $mp->score,
                'result' => $mp->result?->value,
                'participant_type' => $mp->competitionParticipant?->participant_type?->value,
                'participant_id' => $mp->competitionParticipant?->participant_id,
                'participant_name' => $mp->competitionParticipant?->participant?->name,
                'external_reference_id' => $mp->competitionParticipant?->participant?->external_reference_id,
                'source_system' => $mp->competitionParticipant?->participant?->source_system,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
