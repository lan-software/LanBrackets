<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompetitionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'visibility' => $this->visibility->value,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'settings' => $this->settings,
            'metadata' => $this->metadata,
            'external_reference_id' => $this->external_reference_id,
            'source_system' => $this->source_system,
            'share_token' => $this->share_token,
            'participants_count' => $this->whenCounted('participants'),
            'stages_count' => $this->whenCounted('stages'),
            'stages' => CompetitionStageResource::collection($this->whenLoaded('stages')),
            'participants' => CompetitionParticipantResource::collection($this->whenLoaded('participants')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
