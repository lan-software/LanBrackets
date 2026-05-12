<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serialize a team for the v1 API.
 *
 * @see docs/mil-std-498/SRS.md COMP-F-018
 * @see docs/mil-std-498/IDD.md §3.8.1
 */
class TeamResource extends JsonResource
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
            'tag' => $this->tag,
            'description' => $this->description,
            'status' => $this->status,
            'external_reference_id' => $this->external_reference_id,
            'source_system' => $this->source_system,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
