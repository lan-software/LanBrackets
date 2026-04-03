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
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
