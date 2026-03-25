<?php

namespace App\Models;

use App\Enums\ParticipantStatus;
use App\Enums\ParticipantType;
use Database\Factories\CompetitionParticipantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'competition_id', 'participant_type', 'participant_id',
    'seed', 'status', 'checked_in_at', 'metadata',
])]
class CompetitionParticipant extends Model
{
    /** @use HasFactory<CompetitionParticipantFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'participant_type' => ParticipantType::class,
            'status' => ParticipantStatus::class,
            'seed' => 'integer',
            'checked_in_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<Competition, $this> */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    /** @return MorphTo<Model, $this> */
    public function participant(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return HasMany<MatchParticipant, $this> */
    public function matchParticipants(): HasMany
    {
        return $this->hasMany(MatchParticipant::class);
    }

    /** @return HasMany<CompetitionStageGroupMember, $this> */
    public function groupMemberships(): HasMany
    {
        return $this->hasMany(CompetitionStageGroupMember::class);
    }
}
