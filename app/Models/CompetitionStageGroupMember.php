<?php

namespace App\Models;

use Database\Factories\CompetitionStageGroupMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'competition_stage_group_id', 'competition_participant_id', 'seed',
])]
class CompetitionStageGroupMember extends Model
{
    /** @use HasFactory<CompetitionStageGroupMemberFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seed' => 'integer',
        ];
    }

    /** @return BelongsTo<CompetitionStageGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(CompetitionStageGroup::class, 'competition_stage_group_id');
    }

    /** @return BelongsTo<CompetitionParticipant, $this> */
    public function competitionParticipant(): BelongsTo
    {
        return $this->belongsTo(CompetitionParticipant::class);
    }
}
