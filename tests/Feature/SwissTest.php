<?php

use App\Domain\Competition\Formats\Swiss\Generator;
use App\Domain\Competition\Formats\Swiss\Resolver;
use App\Domain\Competition\Formats\Swiss\Ruleset;
use App\Domain\Competition\Services\FormatRegistry;
use App\Enums\MatchResult;
use App\Enums\MatchStatus;
use App\Enums\StageType;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\CompetitionParticipant;
use App\Models\CompetitionStage;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helper ───

function createSwissStageWithParticipants(int $count, ?int $totalRounds = null): CompetitionStage
{
    $competition = Competition::factory()->tournament()->create();

    $settings = $totalRounds !== null ? ['total_rounds' => $totalRounds] : null;

    $stage = CompetitionStage::factory()->swiss()->create([
        'competition_id' => $competition->id,
        'order' => 1,
        'settings' => $settings,
    ]);

    for ($i = 1; $i <= $count; $i++) {
        CompetitionParticipant::factory()->forTeam(Team::factory()->create())->create([
            'competition_id' => $competition->id,
            'seed' => $i,
        ]);
    }

    return $stage;
}

function resolveAllPendingInRound(CompetitionStage $stage, int $round): void
{
    $matches = CompetitionMatch::query()
        ->where('competition_stage_id', $stage->id)
        ->where('round_number', $round)
        ->where('status', MatchStatus::Pending)
        ->get();

    foreach ($matches as $match) {
        $match->matchParticipants->firstWhere('slot', 1)->update(['score' => 3]);
        $match->matchParticipants->firstWhere('slot', 2)->update(['score' => 1]);
        (new Resolver)->resolve($match);
    }
}

// ─── Generator Tests ───

it('rejects fewer than 4 participants', function () {
    $stage = createSwissStageWithParticipants(3);

    app(Generator::class)->generate($stage);
})->throws(InvalidArgumentException::class, 'at least 4 participants');

it('generates only round 1 initially', function () {
    $stage = createSwissStageWithParticipants(8);

    app(Generator::class)->generate($stage);

    $matches = CompetitionMatch::where('competition_stage_id', $stage->id)->get();

    // 8p → 4 matches in round 1
    expect($matches)->toHaveCount(4)
        ->and($matches->pluck('round_number')->unique()->toArray())->toBe([1]);
});

it('generates correct round 1 pairings by seed', function () {
    $stage = createSwissStageWithParticipants(8);

    app(Generator::class)->generate($stage);

    $matches = CompetitionMatch::where('competition_stage_id', $stage->id)
        ->orderBy('sequence')
        ->get();

    $participants = $stage->competition->participants()->orderBy('seed')->get();

    // Seed 1 vs 2, 3 vs 4, etc.
    $m1Ids = $matches[0]->matchParticipants->pluck('competition_participant_id')->sort()->values();
    expect($m1Ids->toArray())->toBe([$participants[0]->id, $participants[1]->id]);
});

it('auto-calculates total rounds as ceil(log2(N))', function () {
    $stage = createSwissStageWithParticipants(8);

    app(Generator::class)->generate($stage);

    $stage->refresh();

    // ceil(log2(8)) = 3
    expect($stage->settings['total_rounds'])->toBe(3);
});

it('respects custom total_rounds setting', function () {
    $stage = createSwissStageWithParticipants(8, totalRounds: 4);

    app(Generator::class)->generate($stage);

    $stage->refresh();

    expect($stage->settings['total_rounds'])->toBe(4);
});

it('handles BYE for odd participant count in round 1', function () {
    $stage = createSwissStageWithParticipants(7);

    app(Generator::class)->generate($stage);

    $matches = CompetitionMatch::where('competition_stage_id', $stage->id)->get();

    // 7p → 3 real matches + 1 BYE match = 4 matches
    expect($matches)->toHaveCount(4);

    $byeMatches = $matches->where('status', MatchStatus::Finished);
    expect($byeMatches)->toHaveCount(1)
        ->and($byeMatches->first()->winner_participant_id)->not->toBeNull();
});

// ─── Resolver Tests ───

it('resolves a single match', function () {
    $stage = createSwissStageWithParticipants(8);

    app(Generator::class)->generate($stage);

    $match = CompetitionMatch::where('competition_stage_id', $stage->id)
        ->where('status', MatchStatus::Pending)
        ->first();

    $p1 = $match->matchParticipants->firstWhere('slot', 1);
    $p2 = $match->matchParticipants->firstWhere('slot', 2);

    $p1->update(['score' => 3]);
    $p2->update(['score' => 1]);

    (new Resolver)->resolve($match);

    $match->refresh();

    expect($match->status)->toBe(MatchStatus::Finished)
        ->and($match->winner_participant_id)->toBe($p1->competition_participant_id)
        ->and($p1->fresh()->result)->toBe(MatchResult::Win)
        ->and($p2->fresh()->result)->toBe(MatchResult::Loss);
});

it('rejects draws', function () {
    $stage = createSwissStageWithParticipants(8);

    app(Generator::class)->generate($stage);

    $match = CompetitionMatch::where('competition_stage_id', $stage->id)
        ->where('status', MatchStatus::Pending)
        ->first();

    $match->matchParticipants->firstWhere('slot', 1)->update(['score' => 2]);
    $match->matchParticipants->firstWhere('slot', 2)->update(['score' => 2]);

    (new Resolver)->resolve($match);
})->throws(InvalidArgumentException::class, 'tied');

it('generates round 2 after all round 1 matches finish', function () {
    $stage = createSwissStageWithParticipants(8);

    app(Generator::class)->generate($stage);

    resolveAllPendingInRound($stage, 1);

    $round2Matches = CompetitionMatch::where('competition_stage_id', $stage->id)
        ->where('round_number', 2)
        ->get();

    expect($round2Matches)->toHaveCount(4);
});

it('does not generate next round until all current round matches finish', function () {
    $stage = createSwissStageWithParticipants(8);

    app(Generator::class)->generate($stage);

    // Resolve only the first match
    $match = CompetitionMatch::where('competition_stage_id', $stage->id)
        ->where('round_number', 1)
        ->where('status', MatchStatus::Pending)
        ->first();

    $match->matchParticipants->firstWhere('slot', 1)->update(['score' => 3]);
    $match->matchParticipants->firstWhere('slot', 2)->update(['score' => 1]);
    (new Resolver)->resolve($match);

    $round2Count = CompetitionMatch::where('competition_stage_id', $stage->id)
        ->where('round_number', 2)
        ->count();

    expect($round2Count)->toBe(0);
});

it('avoids repeat matchups across rounds', function () {
    $stage = createSwissStageWithParticipants(8);

    app(Generator::class)->generate($stage);

    resolveAllPendingInRound($stage, 1);

    $allMatches = CompetitionMatch::where('competition_stage_id', $stage->id)
        ->with('matchParticipants')
        ->get();

    $matchups = [];
    foreach ($allMatches as $match) {
        $ids = $match->matchParticipants->pluck('competition_participant_id')->sort()->values()->implode('-');
        if ($match->matchParticipants->count() === 2) {
            $matchups[] = $ids;
        }
    }

    // All matchups should be unique
    expect(count($matchups))->toBe(count(array_unique($matchups)));
});

it('plays through a full 8-participant Swiss tournament', function () {
    $stage = createSwissStageWithParticipants(8);

    app(Generator::class)->generate($stage);

    $totalRounds = $stage->fresh()->settings['total_rounds']; // 3

    for ($round = 1; $round <= $totalRounds; $round++) {
        resolveAllPendingInRound($stage, $round);
    }

    $allMatches = CompetitionMatch::where('competition_stage_id', $stage->id)->get();

    // 3 rounds * 4 matches = 12 matches
    expect($allMatches)->toHaveCount(12)
        ->and($allMatches->where('status', MatchStatus::Finished))->toHaveCount(12);

    // Should not have generated a round 4
    $round4 = CompetitionMatch::where('competition_stage_id', $stage->id)
        ->where('round_number', 4)
        ->count();
    expect($round4)->toBe(0);
});

it('handles BYE rotation for odd participant count across rounds', function () {
    $stage = createSwissStageWithParticipants(5);

    app(Generator::class)->generate($stage);

    $totalRounds = $stage->fresh()->settings['total_rounds'];

    for ($round = 1; $round <= $totalRounds; $round++) {
        resolveAllPendingInRound($stage, $round);
    }

    // All matches should be finished
    $pending = CompetitionMatch::where('competition_stage_id', $stage->id)
        ->where('status', '!=', MatchStatus::Finished)
        ->count();

    expect($pending)->toBe(0);
});

// ─── Ruleset Tests ───

it('provides default settings', function () {
    $ruleset = new Ruleset;

    expect($ruleset->defaults())->toBe([
        'best_of' => 1,
        'total_rounds' => null,
        'tiebreaker' => 'buchholz',
    ]);
});

it('validates best_of must be odd', function () {
    $ruleset = new Ruleset;

    expect($ruleset->validate(['best_of' => 3]))->toBeEmpty()
        ->and($ruleset->validate(['best_of' => 2]))->toHaveKey('best_of');
});

it('validates total_rounds must be positive integer or null', function () {
    $ruleset = new Ruleset;

    expect($ruleset->validate(['total_rounds' => 3]))->toBeEmpty()
        ->and($ruleset->validate(['total_rounds' => null]))->toBeEmpty()
        ->and($ruleset->validate(['total_rounds' => 0]))->toHaveKey('total_rounds');
});

it('applies defaults to a stage', function () {
    $stage = createSwissStageWithParticipants(4);
    $stage->update(['settings' => null]);

    (new Ruleset)->apply($stage);

    expect($stage->fresh()->settings)->toBe([
        'best_of' => 1,
        'total_rounds' => null,
        'tiebreaker' => 'buchholz',
    ]);
});

// ─── FormatRegistry Integration ───

it('resolves swiss via format registry', function () {
    $registry = new FormatRegistry;

    expect($registry->hasFormat(StageType::Swiss))->toBeTrue()
        ->and($registry->generator(StageType::Swiss))->toBeInstanceOf(Generator::class)
        ->and($registry->resolver(StageType::Swiss))->toBeInstanceOf(Resolver::class)
        ->and($registry->ruleset(StageType::Swiss))->toBeInstanceOf(Ruleset::class);
});
