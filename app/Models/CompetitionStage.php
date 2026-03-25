<?php

namespace App\Models;

use App\Enums\StageStatus;
use App\Enums\StageType;
use Database\Factories\CompetitionStageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'competition_id', 'name', 'slug', 'order', 'stage_type',
    'status', 'settings', 'progression_meta',
])]
class CompetitionStage extends Model
{
    /** @use HasFactory<CompetitionStageFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stage_type' => StageType::class,
            'status' => StageStatus::class,
            'settings' => 'array',
            'progression_meta' => 'array',
            'order' => 'integer',
        ];
    }

    /** @return BelongsTo<Competition, $this> */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    /** @return HasMany<CompetitionMatch, $this> */
    public function matches(): HasMany
    {
        return $this->hasMany(CompetitionMatch::class);
    }

    /** @return HasMany<CompetitionStageGroup, $this> */
    public function groups(): HasMany
    {
        return $this->hasMany(CompetitionStageGroup::class)->orderBy('sequence');
    }

    /**
     * TODO: Resolve format generator class from stage_type + settings
     * TODO: Resolve format resolver class for bracket progression
     * TODO: Implement progression logic between stages via progression_meta
     */
}
