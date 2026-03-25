<?php

namespace App\Models;

use Database\Factories\MatchConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Defines the match graph structure for bracket progression.
 *
 * source_outcome values:
 *   - 'winner'       → the winner of source_match advances
 *   - 'loser'        → the loser of source_match advances (e.g. double elimination)
 *   - 'placement_N'  → the Nth placed participant advances (e.g. group stage)
 */
#[Fillable([
    'source_match_id', 'source_outcome', 'target_match_id', 'target_slot',
])]
class MatchConnection extends Model
{
    /** @use HasFactory<MatchConnectionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target_slot' => 'integer',
        ];
    }

    /** @return BelongsTo<CompetitionMatch, $this> */
    public function sourceMatch(): BelongsTo
    {
        return $this->belongsTo(CompetitionMatch::class, 'source_match_id');
    }

    /** @return BelongsTo<CompetitionMatch, $this> */
    public function targetMatch(): BelongsTo
    {
        return $this->belongsTo(CompetitionMatch::class, 'target_match_id');
    }

    /**
     * TODO: Implement automatic participant advancement when source match finishes
     * TODO: Validate that source_outcome is valid for the source match's stage type
     */
}
