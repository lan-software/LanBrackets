<?php

namespace App\Models;

use App\Enums\MatchStatus;
use App\Enums\StageStatus;
use App\Enums\StageType;
use Database\Factories\CompetitionStageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
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
     * Check if all matches in this stage are finished.
     *
     * For Swiss format, also verifies that all configured rounds have been played.
     */
    public function isComplete(): bool
    {
        if ($this->status === StageStatus::Completed) {
            return true;
        }

        if ($this->status !== StageStatus::Running) {
            return false;
        }

        // Swiss: check that we've reached the configured total rounds
        if ($this->stage_type === StageType::Swiss) {
            $totalRounds = $this->settings['total_rounds'] ?? 3;
            $maxFinishedRound = $this->matches()
                ->where('status', MatchStatus::Finished)
                ->max('round_number');

            if ($maxFinishedRound === null || $maxFinishedRound < $totalRounds) {
                return false;
            }
        }

        return $this->matches()
            ->where('status', '!=', MatchStatus::Finished)
            ->where('status', '!=', MatchStatus::Cancelled)
            ->doesntExist();
    }

    /**
     * Get the participants relevant to this stage.
     *
     * For the first stage (no qualified_seeds), returns all competition participants.
     * For subsequent stages, returns only qualified participants re-ordered by new seed.
     *
     * @return Collection<int, CompetitionParticipant>
     */
    public function getStageParticipants(): Collection
    {
        $qualifiedSeeds = $this->settings['qualified_seeds'] ?? null;

        if ($qualifiedSeeds === null) {
            return $this->competition->participants()
                ->whereNull('metadata->disqualified')
                ->orderBy('seed')
                ->get();
        }

        $seedMap = collect($qualifiedSeeds)
            ->pluck('new_seed', 'participant_id');

        return $this->competition->participants()
            ->whereIn('id', $seedMap->keys()->all())
            ->get()
            ->sortBy(fn (CompetitionParticipant $p) => $seedMap[$p->id])
            ->values();
    }
}
