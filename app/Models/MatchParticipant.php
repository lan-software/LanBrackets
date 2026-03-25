<?php

namespace App\Models;

use App\Enums\MatchResult;
use Database\Factories\MatchParticipantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'match_id', 'competition_participant_id', 'slot', 'score', 'result', 'metadata',
])]
class MatchParticipant extends Model
{
    /** @use HasFactory<MatchParticipantFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'slot' => 'integer',
            'score' => 'integer',
            'result' => MatchResult::class,
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<CompetitionMatch, $this> */
    public function match(): BelongsTo
    {
        return $this->belongsTo(CompetitionMatch::class, 'match_id');
    }

    /** @return BelongsTo<CompetitionParticipant, $this> */
    public function competitionParticipant(): BelongsTo
    {
        return $this->belongsTo(CompetitionParticipant::class);
    }
}
