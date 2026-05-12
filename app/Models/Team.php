<?php

namespace App\Models;

use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @see docs/mil-std-498/SRS.md COMP-F-018
 */
#[Fillable(['name', 'slug', 'tag', 'description', 'status', 'external_reference_id', 'source_system'])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    /**
     * Scope a query to a team identified by its origin tuple.
     *
     * @see docs/mil-std-498/SRS.md COMP-F-018
     *
     * @param  Builder<Team>  $q
     * @return Builder<Team>
     */
    public function scopeBySource(Builder $q, string $sourceSystem, string $externalReferenceId): Builder
    {
        return $q->where('source_system', $sourceSystem)
            ->where('external_reference_id', $externalReferenceId);
    }

    /** @return HasMany<TeamMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    /** @return HasMany<TeamMember, $this> */
    public function activeMembers(): HasMany
    {
        return $this->hasMany(TeamMember::class)->whereNull('left_at');
    }

    /** @return HasMany<TeamMember, $this> */
    public function captains(): HasMany
    {
        return $this->hasMany(TeamMember::class)->where('is_captain', true)->whereNull('left_at');
    }

    /** @return HasMany<CompetitionParticipant, $this> */
    public function competitionParticipants(): HasMany
    {
        return $this->hasMany(CompetitionParticipant::class, 'participant_id')
            ->where('participant_type', 'team');
    }
}
