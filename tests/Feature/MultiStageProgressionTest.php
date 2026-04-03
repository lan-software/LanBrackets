<?php

use App\Actions\AddCompetitionParticipantAction;
use App\Actions\AddCompetitionStageAction;
use App\Actions\CreateCompetitionAction;
use App\Actions\GenerateBracketAction;
use App\Actions\ReportMatchResultAction;
use App\Domain\Competition\Formats\GroupStage\StandingsCalculator as GSStandingsCalculator;
use App\Domain\Competition\Formats\Swiss\StandingsCalculator as SwissStandingsCalculator;
use App\Domain\Competition\Services\FormatRegistry;
use App\Enums\CompetitionStatus;
use App\Enums\CompetitionType;
use App\Enums\MatchStatus;
use App\Enums\StageStatus;
use App\Enums\StageType;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\CompetitionParticipant;
use App\Models\CompetitionStage;
use App\Models\CompetitionStageGroup;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ───

function createMultiStageCompetition(
    StageType $firstStageType,
    array $firstStageSettings,
    StageType $secondStageType,
    array $secondStageSettings,
    array $progressionMeta,
    int $teamCount,
): Competition {
    $createCompetition = app(CreateCompetitionAction::class);
    $addStage = app(AddCompetitionStageAction::class);
    $addParticipant = app(AddCompetitionParticipantAction::class);

    $competition = $createCompetition->execute(
        name: 'Test Multi-Stage',
        type: CompetitionType::Tournament,
        stageType: $firstStageType,
        options: ['settings' => $firstStageSettings],
    );

    $addStage->execute($competition, $secondStageType, [
        'settings' => $secondStageSettings,
        'progression_meta' => $progressionMeta,
    ]);

    for ($i = 1; $i <= $teamCount; $i++) {
        $addParticipant->execute(
            $competition,
            Team::factory()->create(),
            $i,
        );
    }

    return $competition->fresh(['stages']);
}

function playAllStageMatches(CompetitionStage $stage): void
{
    $reportResult = app(ReportMatchResultAction::class);
    $safety = 0;

    while ($safety++ < 500) {
        $match = CompetitionMatch::query()
            ->where('competition_stage_id', $stage->id)
            ->where('status', MatchStatus::Pending)
            ->orderBy('round_number')
            ->orderBy('sequence')
            ->withCount('matchParticipants')
            ->get()
            ->where('match_participants_count', 2)
            ->first();

        if ($match === null) {
            break;
        }

        $allowDraws = $stage->settings['allow_draws'] ?? false;

        // Generate scores — avoid draws for non-draw formats
        $score1 = rand(1, 5);
        $score2 = rand(0, max(0, $score1 - 1));

        if ($allowDraws && rand(1, 5) === 1) {
            $drawScore = rand(0, 3);
            $scores = [1 => $drawScore, 2 => $drawScore];
        } else {
            if (rand(0, 1) === 0) {
                $scores = [1 => $score1, 2 => $score2];
            } else {
                $scores = [1 => $score2, 2 => $score1];
            }

            if (! $allowDraws && $scores[1] === $scores[2]) {
                $scores[1]++;
            }
        }

        $reportResult->execute($match, $scores);
    }
}

// ─── Group Stage → Single Elimination ───

it('advances top 2 per group from group stage to SE playoffs', function () {
    $competition = createMultiStageCompetition(
        firstStageType: StageType::GroupStage,
        firstStageSettings: ['group_count' => 4, 'allow_draws' => true],
        secondStageType: StageType::SingleElimination,
        secondStageSettings: ['third_place_match' => true],
        progressionMeta: ['per_group' => 2],
        teamCount: 16,
    );

    $groupStage = $competition->stages->firstWhere('order', 1);
    $playoffs = $competition->stages->firstWhere('order', 2);

    expect($groupStage->stage_type)->toBe(StageType::GroupStage)
        ->and($playoffs->stage_type)->toBe(StageType::SingleElimination)
        ->and($playoffs->status)->toBe(StageStatus::Pending);

    // Verify progression_meta on group stage
    expect($groupStage->progression_meta)->toHaveKey('per_group')
        ->and($groupStage->progression_meta['per_group'])->toBe(2);

    // Generate and play through group stage
    app(GenerateBracketAction::class)->execute($groupStage);
    playAllStageMatches($groupStage);

    // Group stage should be completed
    $groupStage->refresh();
    expect($groupStage->status)->toBe(StageStatus::Completed)
        ->and($groupStage->progression_meta)->toHaveKey('standings')
        ->and($groupStage->progression_meta)->toHaveKey('qualified_participants');

    // 4 groups × 2 per group = 8 qualifiers
    $qualifiers = $groupStage->progression_meta['qualified_participants'];
    expect($qualifiers)->toHaveCount(8);

    // Playoffs should have been auto-generated
    $playoffs->refresh();
    expect($playoffs->status)->toBe(StageStatus::Running);

    // Playoffs should have matches for 8 participants
    $playoffMatches = CompetitionMatch::where('competition_stage_id', $playoffs->id)->count();
    // 8-team SE with 3rd place: 4+2+1+1 = 8 matches (but 3rd place match is extra at same round)
    expect($playoffMatches)->toBeGreaterThanOrEqual(7);

    // Play through playoffs
    playAllStageMatches($playoffs);

    $playoffs->refresh();
    expect($playoffs->status)->toBe(StageStatus::Completed);

    // Competition should be finished
    $competition->refresh();
    expect($competition->status)->toBe(CompetitionStatus::Finished);
});

it('seeds playoff participants based on group stage standings', function () {
    $competition = createMultiStageCompetition(
        firstStageType: StageType::GroupStage,
        firstStageSettings: ['group_count' => 2, 'allow_draws' => false],
        secondStageType: StageType::SingleElimination,
        secondStageSettings: [],
        progressionMeta: ['per_group' => 2],
        teamCount: 8,
    );

    $groupStage = $competition->stages->firstWhere('order', 1);

    app(GenerateBracketAction::class)->execute($groupStage);
    playAllStageMatches($groupStage);

    $groupStage->refresh();
    $qualifiers = $groupStage->progression_meta['qualified_participants'];

    // 2 groups × 2 = 4 qualifiers, seeded 1-4
    expect($qualifiers)->toHaveCount(4);
    expect($qualifiers[0]['new_seed'])->toBe(1); // Group A winner
    expect($qualifiers[1]['new_seed'])->toBe(2); // Group B winner
    expect($qualifiers[2]['new_seed'])->toBe(3); // Group A runner-up
    expect($qualifiers[3]['new_seed'])->toBe(4); // Group B runner-up
});

// ─── Swiss → Single Elimination ───

it('advances top 8 from swiss to SE playoffs', function () {
    $competition = createMultiStageCompetition(
        firstStageType: StageType::Swiss,
        firstStageSettings: ['total_rounds' => 3],
        secondStageType: StageType::SingleElimination,
        secondStageSettings: [],
        progressionMeta: ['advance_count' => 8],
        teamCount: 16,
    );

    $swissStage = $competition->stages->firstWhere('order', 1);
    $playoffs = $competition->stages->firstWhere('order', 2);

    app(GenerateBracketAction::class)->execute($swissStage);

    // Play all 3 Swiss rounds
    for ($round = 1; $round <= 3; $round++) {
        playAllStageMatches($swissStage);
    }

    $swissStage->refresh();
    expect($swissStage->status)->toBe(StageStatus::Completed);

    $qualifiers = $swissStage->progression_meta['qualified_participants'];
    expect($qualifiers)->toHaveCount(8);

    // Playoffs should be running
    $playoffs->refresh();
    expect($playoffs->status)->toBe(StageStatus::Running);

    // Play through SE playoffs
    playAllStageMatches($playoffs);

    $playoffs->refresh();
    expect($playoffs->status)->toBe(StageStatus::Completed);

    $competition->refresh();
    expect($competition->status)->toBe(CompetitionStatus::Finished);
});

// ─── Round Robin → Single Elimination ───

it('advances top 4 from round robin to SE playoffs', function () {
    $competition = createMultiStageCompetition(
        firstStageType: StageType::RoundRobin,
        firstStageSettings: ['allow_draws' => false],
        secondStageType: StageType::SingleElimination,
        secondStageSettings: [],
        progressionMeta: ['advance_count' => 4],
        teamCount: 6,
    );

    $rrStage = $competition->stages->firstWhere('order', 1);

    app(GenerateBracketAction::class)->execute($rrStage);
    playAllStageMatches($rrStage);

    $rrStage->refresh();
    expect($rrStage->status)->toBe(StageStatus::Completed);

    $qualifiers = $rrStage->progression_meta['qualified_participants'];
    expect($qualifiers)->toHaveCount(4);

    $playoffs = $competition->stages->firstWhere('order', 2);
    $playoffs->refresh();
    expect($playoffs->status)->toBe(StageStatus::Running);

    playAllStageMatches($playoffs);

    $competition->refresh();
    expect($competition->status)->toBe(CompetitionStatus::Finished);
});

// ─── Stage Completion Detection ───

it('auto-completes a single-stage SE tournament', function () {
    $competition = Competition::factory()->tournament()->create();

    $stage = CompetitionStage::factory()->singleElimination()->create([
        'competition_id' => $competition->id,
        'order' => 1,
    ]);

    for ($i = 1; $i <= 4; $i++) {
        CompetitionParticipant::factory()->forTeam(Team::factory()->create())->create([
            'competition_id' => $competition->id,
            'seed' => $i,
        ]);
    }

    app(GenerateBracketAction::class)->execute($stage);
    playAllStageMatches($stage);

    $stage->refresh();
    expect($stage->status)->toBe(StageStatus::Completed);

    $competition->refresh();
    expect($competition->status)->toBe(CompetitionStatus::Finished);
});

it('auto-completes a group stage when all matches finish', function () {
    $competition = Competition::factory()->tournament()->create();

    $stage = CompetitionStage::factory()->groupStage()->create([
        'competition_id' => $competition->id,
        'order' => 1,
        'settings' => ['group_count' => 2, 'allow_draws' => true],
    ]);

    for ($i = 1; $i <= 8; $i++) {
        CompetitionParticipant::factory()->forTeam(Team::factory()->create())->create([
            'competition_id' => $competition->id,
            'seed' => $i,
        ]);
    }

    app(GenerateBracketAction::class)->execute($stage);
    playAllStageMatches($stage);

    $stage->refresh();
    expect($stage->status)->toBe(StageStatus::Completed);
});

// ─── Standings Calculators ───

it('calculates group stage standings with points', function () {
    $competition = Competition::factory()->tournament()->create();

    $stage = CompetitionStage::factory()->groupStage()->create([
        'competition_id' => $competition->id,
        'order' => 1,
        'settings' => ['group_count' => 2, 'allow_draws' => false, 'points_win' => 3, 'points_loss' => 0],
    ]);

    for ($i = 1; $i <= 8; $i++) {
        CompetitionParticipant::factory()->forTeam(Team::factory()->create())->create([
            'competition_id' => $competition->id,
            'seed' => $i,
        ]);
    }

    app(GenerateBracketAction::class)->execute($stage);
    playAllStageMatches($stage);

    $calculator = new GSStandingsCalculator;
    $standings = $calculator->calculate($stage);

    // 8 participants should be ranked
    expect($standings)->toHaveCount(8);

    // Interleaved: positions 1-2 are group winners, 3-4 are runners-up, etc.
    expect($standings[0]->placement)->toBe(1)
        ->and($standings[0]->groupId)->not->toBeNull()
        ->and($standings[1]->groupId)->not->toBe($standings[0]->groupId);
});

it('calculates swiss standings with buchholz', function () {
    $competition = Competition::factory()->tournament()->create();

    $stage = CompetitionStage::factory()->swiss()->create([
        'competition_id' => $competition->id,
        'order' => 1,
    ]);

    for ($i = 1; $i <= 8; $i++) {
        CompetitionParticipant::factory()->forTeam(Team::factory()->create())->create([
            'competition_id' => $competition->id,
            'seed' => $i,
        ]);
    }

    app(GenerateBracketAction::class)->execute($stage);

    $totalRounds = $stage->fresh()->settings['total_rounds'];
    for ($round = 1; $round <= $totalRounds; $round++) {
        playAllStageMatches($stage);
    }

    $calculator = new SwissStandingsCalculator;
    $standings = $calculator->calculate($stage);

    expect($standings)->toHaveCount(8)
        ->and($standings[0]->placement)->toBe(1)
        ->and($standings[0]->wins)->toBeGreaterThan(0)
        ->and($standings[0]->tiebreaker)->toBeGreaterThanOrEqual(0);

    // Verify ordering: wins should be descending
    for ($i = 0; $i < count($standings) - 1; $i++) {
        expect($standings[$i]->wins)->toBeGreaterThanOrEqual($standings[$i + 1]->wins);
    }
});

it('resolves standings calculator via format registry', function () {
    $registry = new FormatRegistry;

    expect($registry->standingsCalculator(StageType::GroupStage))
        ->toBeInstanceOf(GSStandingsCalculator::class)
        ->and($registry->standingsCalculator(StageType::Swiss))
        ->toBeInstanceOf(SwissStandingsCalculator::class);
});

// ─── Large Multi-Stage (29 teams) ───

it('plays through a full 29-team group stage to SE progression', function () {
    $competition = createMultiStageCompetition(
        firstStageType: StageType::GroupStage,
        firstStageSettings: ['group_count' => 4, 'allow_draws' => true],
        secondStageType: StageType::SingleElimination,
        secondStageSettings: [],
        progressionMeta: ['per_group' => 2],
        teamCount: 29,
    );

    $groupStage = $competition->stages->firstWhere('order', 1);

    app(GenerateBracketAction::class)->execute($groupStage);

    // Verify 4 groups created
    $groups = CompetitionStageGroup::where('competition_stage_id', $groupStage->id)->get();
    expect($groups)->toHaveCount(4);

    playAllStageMatches($groupStage);

    $groupStage->refresh();
    expect($groupStage->status)->toBe(StageStatus::Completed);

    $qualifiers = $groupStage->progression_meta['qualified_participants'];
    expect($qualifiers)->toHaveCount(8);

    $playoffs = $competition->stages->firstWhere('order', 2);
    $playoffs->refresh();
    expect($playoffs->status)->toBe(StageStatus::Running);

    playAllStageMatches($playoffs);

    $competition->refresh();
    expect($competition->status)->toBe(CompetitionStatus::Finished);
});
