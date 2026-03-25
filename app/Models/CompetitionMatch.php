<?php

namespace App\Models;

use App\Enums\MatchStatus;
use Database\Factories\CompetitionMatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Named CompetitionMatch because 'match' is a PHP reserved keyword.
 * Table name remains 'matches' for clarity in the database.
 */
#[Fillable([
    'competition_id', 'competition_stage_id', 'round_number', 'sequence',
    'status', 'scheduled_at', 'started_at', 'finished_at',
    'winner_participant_id', 'loser_participant_id', 'settings',
])]
class CompetitionMatch extends Model
{
    /** @use HasFactory<CompetitionMatchFactory> */
    use HasFactory;

    protected $table = 'matches';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MatchStatus::class,
            'round_number' => 'integer',
            'sequence' => 'integer',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    /** @return BelongsTo<Competition, $this> */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    /** @return BelongsTo<CompetitionStage, $this> */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(CompetitionStage::class, 'competition_stage_id');
    }

    /** @return HasMany<MatchParticipant, $this> */
    public function matchParticipants(): HasMany
    {
        return $this->hasMany(MatchParticipant::class, 'match_id');
    }

    /** @return BelongsTo<CompetitionParticipant, $this> */
    public function winner(): BelongsTo
    {
        return $this->belongsTo(CompetitionParticipant::class, 'winner_participant_id');
    }

    /** @return BelongsTo<CompetitionParticipant, $this> */
    public function loser(): BelongsTo
    {
        return $this->belongsTo(CompetitionParticipant::class, 'loser_participant_id');
    }

    /** @return HasMany<MatchConnection, $this> */
    public function outgoingConnections(): HasMany
    {
        return $this->hasMany(MatchConnection::class, 'source_match_id');
    }

    /** @return HasMany<MatchConnection, $this> */
    public function incomingConnections(): HasMany
    {
        return $this->hasMany(MatchConnection::class, 'target_match_id');
    }

    /**
     * TODO: Implement match result reporting via a dedicated action/service
     * TODO: Trigger bracket progression via MatchConnection graph after result
     */
}
