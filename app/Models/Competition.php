<?php

namespace App\Models;

use App\Enums\CompetitionStatus;
use App\Enums\CompetitionType;
use App\Enums\CompetitionVisibility;
use Database\Factories\CompetitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'slug', 'description', 'type', 'status', 'visibility',
    'starts_at', 'ends_at', 'settings', 'published_at', 'share_token',
    'external_reference_id', 'source_system', 'metadata',
])]
class Competition extends Model
{
    /** @use HasFactory<CompetitionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CompetitionType::class,
            'status' => CompetitionStatus::class,
            'visibility' => CompetitionVisibility::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'settings' => 'array',
            'published_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /** @return HasMany<CompetitionStage, $this> */
    public function stages(): HasMany
    {
        return $this->hasMany(CompetitionStage::class)->orderBy('order');
    }

    /** @return HasMany<CompetitionParticipant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(CompetitionParticipant::class);
    }

    /** @return HasMany<CompetitionMatch, $this> */
    public function matches(): HasMany
    {
        return $this->hasMany(CompetitionMatch::class);
    }

    public function isPubliclyVisible(): bool
    {
        return $this->visibility === CompetitionVisibility::Public
            && $this->published_at !== null;
    }

    public function isAccessibleViaToken(string $token): bool
    {
        return $this->share_token !== null && $this->share_token === $token;
    }

    /**
     * TODO: Add public access routes for iframe/OBS embedding
     * TODO: Add API resources for public rendering
     * TODO: Implement share token generation logic
     */
}
