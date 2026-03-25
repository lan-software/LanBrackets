<?php

namespace App\Models;

use Database\Factories\CompetitionStageGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'competition_stage_id', 'name', 'slug', 'sequence', 'settings',
])]
class CompetitionStageGroup extends Model
{
    /** @use HasFactory<CompetitionStageGroupFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'settings' => 'array',
        ];
    }

    /** @return BelongsTo<CompetitionStage, $this> */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(CompetitionStage::class, 'competition_stage_id');
    }

    /** @return HasMany<CompetitionStageGroupMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(CompetitionStageGroupMember::class)->orderBy('seed');
    }

    /**
     * TODO: Implement standings calculation for group stage
     * TODO: Implement tiebreaker logic
     * TODO: Support configurable points system (win/draw/loss points)
     */
}
