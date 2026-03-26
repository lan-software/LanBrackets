<?php

use App\Domain\Competition\Formats\DoubleElimination\Generator;
use App\Domain\Competition\Formats\DoubleElimination\Resolver;
use App\Domain\Competition\Formats\DoubleElimination\Ruleset;
use App\Domain\Competition\Services\FormatRegistry;
use App\Enums\MatchStatus;
use App\Enums\StageType;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\CompetitionParticipant;
use App\Models\CompetitionStage;
use App\Models\MatchConnection;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helper ───

function createDEStageWithParticipants(int $count): CompetitionStage
{
    $competition = Competition::factory()->tournament()->create();

    $stage = CompetitionStage::factory()->create([
        'competition_id' => $competition->id,
        'stage_type' => StageType::DoubleElimination,
        'order' => 1,
    ]);

    for ($i = 1; $i <= $count; $i++) {
        CompetitionParticipant::factory()->forTeam(Team::factory()->create())->create([
            'competition_id' => $competition->id,
            'seed' => $i,
        ]);
    }

    return $stage;
}

/**
 * Resolve a match by setting scores and running the resolver.
 */
function resolveMatch(CompetitionMatch $match, int $slot1Score, int $slot2Score): void
{
    $match->matchParticipants->firstWhere('slot', 1)->update(['score' => $slot1Score]);
    $match->matchParticipants->firstWhere('slot', 2)->update(['score' => $slot2Score]);
    (new Resolver)->resolve($match);
}

/**
 * Reload a match with fresh participants.
 */
function freshMatch(int $matchId): CompetitionMatch
{
    return CompetitionMatch::with('matchParticipants')->find($matchId);
}

// ─── Generator Tests ───

it('rejects fewer than 3 participants for double elimination', function () {
    $stage = createDEStageWithParticipants(2);

    (new Generator)->generate($stage);
})->throws(InvalidArgumentException::class, 'at least 3 participants');

it('generates correct structure for 4 participants', function () {
    $stage = createDEStageWithParticipants(4);

    (new Generator)->generate($stage);

    $matches = CompetitionMatch::where('competition_stage_id', $stage->id)->get();

    // WB: R1=2, R2=1 (WB Final) = 3
    // LB: R1(101)=1, R2(102)=1 = 2
    // GF: 1
    // Total: 6
    $wbMatches = $matches->filter(fn ($m) => ($m->settings['bracket_side'] ?? '') === 'winners');
    $lbMatches = $matches->filter(fn ($m) => ($m->settings['bracket_side'] ?? '') === 'losers');
    $gfMatches = $matches->filter(fn ($m) => ($m->settings['bracket_side'] ?? '') === 'grand_final');

    expect($wbMatches)->toHaveCount(3)
        ->and($lbMatches)->toHaveCount(2)
        ->and($gfMatches)->toHaveCount(1)
        ->and($matches)->toHaveCount(6);
});

it('generates correct structure for 8 participants', function () {
    $stage = createDEStageWithParticipants(8);

    (new Generator)->generate($stage);

    $matches = CompetitionMatch::where('competition_stage_id', $stage->id)->get();

    // WB: R1=4, R2=2, R3=1 = 7
    // LB: R1(101)=2, R2(102)=2, R3(103)=1, R4(104)=1 = 6
    // GF: 1
    // Total: 14
    $wbMatches = $matches->filter(fn ($m) => ($m->settings['bracket_side'] ?? '') === 'winners');
    $lbMatches = $matches->filter(fn ($m) => ($m->settings['bracket_side'] ?? '') === 'losers');
    $gfMatches = $matches->filter(fn ($m) => ($m->settings['bracket_side'] ?? '') === 'grand_final');

    expect($wbMatches)->toHaveCount(7)
        ->and($lbMatches)->toHaveCount(6)
        ->and($gfMatches)->toHaveCount(1)
        ->and($matches)->toHaveCount(14);
});

it('seeds first round in winners bracket', function () {
    $stage = createDEStageWithParticipants(4);

    (new Generator)->generate($stage);

    $wbR1 = CompetitionMatch::where('competition_stage_id', $stage->id)
        ->where('round_number', 1)
        ->get();

    // All 4 participants should be seeded in WB R1
    $totalParticipants = $wbR1->flatMap->matchParticipants->count();
    expect($totalParticipants)->toBe(4);
});

it('tags matches with bracket_side metadata', function () {
    $stage = createDEStageWithParticipants(4);

    (new Generator)->generate($stage);

    $matches = CompetitionMatch::where('competition_stage_id', $stage->id)->get();

    $sides = $matches->pluck('settings.bracket_side')->unique()->sort()->values()->toArray();
    expect($sides)->toBe(['grand_final', 'losers', 'winners']);
});

it('creates loser connections from WB to LB', function () {
    $stage = createDEStageWithParticipants(4);

    (new Generator)->generate($stage);

    $loserConnections = MatchConnection::where('source_outcome', 'loser')->get();

    // WB R1 → LB R1 (2 loser connections)
    expect($loserConnections->count())->toBeGreaterThanOrEqual(2);
});

it('creates grand final with connections from WB and LB finals', function () {
    $stage = createDEStageWithParticipants(4);

    (new Generator)->generate($stage);

    $gf = CompetitionMatch::where('competition_stage_id', $stage->id)
        ->where('round_number', 200)
        ->first();

    expect($gf)->not->toBeNull();

    $incoming = MatchConnection::where('target_match_id', $gf->id)->get();
    expect($incoming)->toHaveCount(2)
        ->and($incoming->pluck('target_slot')->sort()->values()->toArray())->toBe([1, 2]);
});

it('handles BYEs in winners bracket for non-power-of-2 counts', function () {
    $stage = createDEStageWithParticipants(3);

    (new Generator)->generate($stage);

    $wbR1Byes = CompetitionMatch::where('competition_stage_id', $stage->id)
        ->where('round_number', 1)
        ->where('status', MatchStatus::Finished)
        ->count();

    expect($wbR1Byes)->toBeGreaterThanOrEqual(1);
});

// ─── Resolver Tests ───

it('resolves a WB match and advances winner/loser', function () {
    $stage = createDEStageWithParticipants(4);

    (new Generator)->generate($stage);

    $wbR1M1 = freshMatch(
        CompetitionMatch::where('competition_stage_id', $stage->id)
            ->where('round_number', 1)
            ->where('sequence', 1)
            ->first()->id
    );

    resolveMatch($wbR1M1, 3, 1);

    $wbR1M1->refresh();
    expect($wbR1M1->status)->toBe(MatchStatus::Finished)
        ->and($wbR1M1->winner_participant_id)->not->toBeNull()
        ->and($wbR1M1->loser_participant_id)->not->toBeNull();

    // Winner should be in WB R2
    $wbFinal = CompetitionMatch::where('competition_stage_id', $stage->id)
        ->where('round_number', 2)
        ->first();
    $wbFinalParticipants = $wbFinal->matchParticipants()->get();
    expect($wbFinalParticipants->count())->toBeGreaterThanOrEqual(1);
});

it('drops loser into losers bracket', function () {
    $stage = createDEStageWithParticipants(4);

    (new Generator)->generate($stage);

    $wbR1M1 = freshMatch(
        CompetitionMatch::where('competition_stage_id', $stage->id)
            ->where('round_number', 1)
            ->where('sequence', 1)
            ->first()->id
    );

    $loserParticipantId = $wbR1M1->matchParticipants->firstWhere('slot', 2)->competition_participant_id;

    resolveMatch($wbR1M1, 5, 2);

    // Loser should now be in an LB match
    $lbMatches = CompetitionMatch::where('competition_stage_id', $stage->id)
        ->where('round_number', '>=', 100)
        ->where('round_number', '<', 200)
        ->get();

    $loserInLB = $lbMatches->flatMap->matchParticipants
        ->contains('competition_participant_id', $loserParticipantId);

    expect($loserInLB)->toBeTrue();
});

it('rejects tied scores in double elimination', function () {
    $stage = createDEStageWithParticipants(4);

    (new Generator)->generate($stage);

    $match = freshMatch(
        CompetitionMatch::where('competition_stage_id', $stage->id)
            ->where('round_number', 1)
            ->where('sequence', 1)
            ->first()->id
    );

    $match->matchParticipants->firstWhere('slot', 1)->update(['score' => 2]);
    $match->matchParticipants->firstWhere('slot', 2)->update(['score' => 2]);

    (new Resolver)->resolve($match);
})->throws(InvalidArgumentException::class, 'tied');

it('plays through a full 4-team double elimination tournament', function () {
    $stage = createDEStageWithParticipants(4);

    (new Generator)->generate($stage);

    // Get all participants for tracking
    $participants = $stage->competition->participants()->orderBy('seed')->get();

    // ── WB Round 1 ──
    $wbR1M1 = freshMatch(
        CompetitionMatch::where('competition_stage_id', $stage->id)
            ->where('round_number', 1)->where('sequence', 1)->first()->id
    );
    resolveMatch($wbR1M1, 3, 1); // Slot 1 wins

    $wbR1M2 = freshMatch(
        CompetitionMatch::where('competition_stage_id', $stage->id)
            ->where('round_number', 1)->where('sequence', 2)->first()->id
    );
    resolveMatch($wbR1M2, 0, 3); // Slot 2 wins

    // ── WB Final (Round 2) ──
    $wbFinal = freshMatch(
        CompetitionMatch::where('competition_stage_id', $stage->id)
            ->where('round_number', 2)->where('sequence', 1)->first()->id
    );
    expect($wbFinal->matchParticipants)->toHaveCount(2);
    resolveMatch($wbFinal, 3, 2); // Slot 1 wins WB

    // ── LB Round 1 (101) ──
    $lbR1 = freshMatch(
        CompetitionMatch::where('competition_stage_id', $stage->id)
            ->where('round_number', 101)->where('sequence', 1)->first()->id
    );
    expect($lbR1->matchParticipants)->toHaveCount(2);
    resolveMatch($lbR1, 3, 0); // Slot 1 wins

    // ── LB Final (102) ──
    $lbFinal = freshMatch(
        CompetitionMatch::where('competition_stage_id', $stage->id)
            ->where('round_number', 102)->where('sequence', 1)->first()->id
    );
    expect($lbFinal->matchParticipants)->toHaveCount(2);
    resolveMatch($lbFinal, 3, 2); // Slot 1 wins LB

    // ── Grand Final (200) ──
    $gf = freshMatch(
        CompetitionMatch::where('competition_stage_id', $stage->id)
            ->where('round_number', 200)->where('sequence', 1)->first()->id
    );
    expect($gf->matchParticipants)->toHaveCount(2);
    resolveMatch($gf, 3, 1); // WB champion wins

    $gf->refresh();
    expect($gf->status)->toBe(MatchStatus::Finished)
        ->and($gf->winner_participant_id)->not->toBeNull();
});

// ─── Ruleset Tests ───

it('provides double elimination default settings', function () {
    $ruleset = new Ruleset;

    expect($ruleset->defaults())->toBe([
        'best_of' => 1,
        'grand_final_reset' => false,
        'third_place_match' => false,
    ]);
});

it('validates double elimination settings', function () {
    $ruleset = new Ruleset;

    expect($ruleset->validate(['best_of' => 5]))->toBeEmpty()
        ->and($ruleset->validate(['best_of' => 4]))->toHaveKey('best_of')
        ->and($ruleset->validate(['grand_final_reset' => 'yes']))->toHaveKey('grand_final_reset');
});

// ─── FormatRegistry Integration ───

it('resolves double elimination via format registry', function () {
    $registry = new FormatRegistry;

    expect($registry->hasFormat(StageType::DoubleElimination))->toBeTrue()
        ->and($registry->generator(StageType::DoubleElimination))->toBeInstanceOf(Generator::class)
        ->and($registry->resolver(StageType::DoubleElimination))->toBeInstanceOf(Resolver::class)
        ->and($registry->ruleset(StageType::DoubleElimination))->toBeInstanceOf(Ruleset::class);
});

it('still reports unimplemented formats correctly', function () {
    $registry = new FormatRegistry;

    expect($registry->hasFormat(StageType::Swiss))->toBeFalse();
});

// ─── Third Place Match Tests ───

function createDEStageWithThirdPlace(int $count): CompetitionStage
{
    $competition = Competition::factory()->tournament()->create();

    $stage = CompetitionStage::factory()->create([
        'competition_id' => $competition->id,
        'stage_type' => StageType::DoubleElimination,
        'order' => 1,
        'settings' => ['third_place_match' => true],
    ]);

    for ($i = 1; $i <= $count; $i++) {
        CompetitionParticipant::factory()->forTeam(Team::factory()->create())->create([
            'competition_id' => $competition->id,
            'seed' => $i,
        ]);
    }

    return $stage;
}

it('generates a 3rd place match for DE 4 participants when enabled', function () {
    $stage = createDEStageWithThirdPlace(4);

    (new Generator)->generate($stage);

    $matches = CompetitionMatch::where('competition_stage_id', $stage->id)->get();

    // WB=3, LB=2, GF=1, 3rd Place=1 = 7 total
    expect($matches)->toHaveCount(7);

    $thirdPlaceMatch = $matches->first(fn ($m) => ($m->settings['bracket_side'] ?? '') === 'third_place');
    expect($thirdPlaceMatch)->not->toBeNull()
        ->and($thirdPlaceMatch->round_number)->toBe(201);

    // Should have loser connections from LB Final and penultimate LB round
    $incoming = MatchConnection::where('target_match_id', $thirdPlaceMatch->id)->get();
    expect($incoming)->toHaveCount(2)
        ->and($incoming->pluck('source_outcome')->unique()->toArray())->toBe(['loser'])
        ->and($incoming->pluck('target_slot')->sort()->values()->toArray())->toBe([1, 2]);
});

it('does not generate a DE 3rd place match when setting is disabled', function () {
    $stage = createDEStageWithParticipants(4);

    (new Generator)->generate($stage);

    $matches = CompetitionMatch::where('competition_stage_id', $stage->id)->get();

    expect($matches)->toHaveCount(6);

    $thirdPlaceMatch = $matches->first(fn ($m) => ($m->settings['bracket_side'] ?? '') === 'third_place');
    expect($thirdPlaceMatch)->toBeNull();
});

it('plays through a full 4-team DE tournament with 3rd place match', function () {
    $stage = createDEStageWithThirdPlace(4);

    (new Generator)->generate($stage);

    // ── WB Round 1 ──
    $wbR1M1 = freshMatch(
        CompetitionMatch::where('competition_stage_id', $stage->id)
            ->where('round_number', 1)->where('sequence', 1)->first()->id
    );
    resolveMatch($wbR1M1, 3, 1);

    $wbR1M2 = freshMatch(
        CompetitionMatch::where('competition_stage_id', $stage->id)
            ->where('round_number', 1)->where('sequence', 2)->first()->id
    );
    resolveMatch($wbR1M2, 0, 3);

    // ── WB Final (Round 2) ──
    $wbFinal = freshMatch(
        CompetitionMatch::where('competition_stage_id', $stage->id)
            ->where('round_number', 2)->where('sequence', 1)->first()->id
    );
    expect($wbFinal->matchParticipants)->toHaveCount(2);
    resolveMatch($wbFinal, 3, 2);

    // ── LB Round 1 (101) ──
    $lbR1 = freshMatch(
        CompetitionMatch::where('competition_stage_id', $stage->id)
            ->where('round_number', 101)->where('sequence', 1)->first()->id
    );
    expect($lbR1->matchParticipants)->toHaveCount(2);
    resolveMatch($lbR1, 3, 0);

    // ── LB Final (102) ──
    $lbFinal = freshMatch(
        CompetitionMatch::where('competition_stage_id', $stage->id)
            ->where('round_number', 102)->where('sequence', 1)->first()->id
    );
    expect($lbFinal->matchParticipants)->toHaveCount(2);
    resolveMatch($lbFinal, 3, 2);

    // ── Grand Final (200) ──
    $gf = freshMatch(
        CompetitionMatch::where('competition_stage_id', $stage->id)
            ->where('round_number', 200)->where('sequence', 1)->first()->id
    );
    expect($gf->matchParticipants)->toHaveCount(2);
    resolveMatch($gf, 3, 1);

    $gf->refresh();
    expect($gf->status)->toBe(MatchStatus::Finished);

    // ── 3rd Place Match (201) ──
    $tp = freshMatch(
        CompetitionMatch::where('competition_stage_id', $stage->id)
            ->where('round_number', 201)->where('sequence', 1)->first()->id
    );
    expect($tp->matchParticipants)->toHaveCount(2);
    resolveMatch($tp, 2, 1);

    $tp->refresh();
    expect($tp->status)->toBe(MatchStatus::Finished)
        ->and($tp->winner_participant_id)->not->toBeNull();

    // All 7 matches should be finished
    $allFinished = CompetitionMatch::where('competition_stage_id', $stage->id)
        ->where('status', MatchStatus::Finished)
        ->count();
    expect($allFinished)->toBe(7);
});

// ─── Grand Final Reset Tests ───

function createDEStageWithGFReset(int $count): CompetitionStage
{
    $competition = Competition::factory()->tournament()->create();

    $stage = CompetitionStage::factory()->create([
        'competition_id' => $competition->id,
        'stage_type' => StageType::DoubleElimination,
        'order' => 1,
        'settings' => ['grand_final_reset' => true],
    ]);

    for ($i = 1; $i <= $count; $i++) {
        CompetitionParticipant::factory()->forTeam(Team::factory()->create())->create([
            'competition_id' => $competition->id,
            'seed' => $i,
        ]);
    }

    return $stage;
}

it('generates a grand final reset match when enabled', function () {
    $stage = createDEStageWithGFReset(4);

    (new Generator)->generate($stage);

    $matches = CompetitionMatch::where('competition_stage_id', $stage->id)->get();

    // WB=3, LB=2, GF=1, GF Reset=1 = 7 total
    expect($matches)->toHaveCount(7);

    $resetMatch = $matches->first(fn ($m) => ($m->settings['bracket_side'] ?? '') === 'grand_final_reset');
    expect($resetMatch)->not->toBeNull()
        ->and($resetMatch->round_number)->toBe(202);

    // Should have winner and loser connections from GF
    $incoming = MatchConnection::where('target_match_id', $resetMatch->id)->get();
    expect($incoming)->toHaveCount(2)
        ->and($incoming->pluck('source_outcome')->sort()->values()->toArray())->toBe(['loser', 'winner'])
        ->and($incoming->pluck('target_slot')->sort()->values()->toArray())->toBe([1, 2]);
});

it('does not generate a GF reset match when setting is disabled', function () {
    $stage = createDEStageWithParticipants(4);

    (new Generator)->generate($stage);

    $matches = CompetitionMatch::where('competition_stage_id', $stage->id)->get();

    expect($matches)->toHaveCount(6);

    $resetMatch = $matches->first(fn ($m) => ($m->settings['bracket_side'] ?? '') === 'grand_final_reset');
    expect($resetMatch)->toBeNull();
});

it('cancels reset match when WB champion wins grand final', function () {
    $stage = createDEStageWithGFReset(4);

    (new Generator)->generate($stage);

    // Play through to GF
    $wbR1M1 = freshMatch(
        CompetitionMatch::where('competition_stage_id', $stage->id)
            ->where('round_number', 1)->where('sequence', 1)->first()->id
    );
    resolveMatch($wbR1M1, 3, 1);

    $wbR1M2 = freshMatch(
        CompetitionMatch::where('competition_stage_id', $stage->id)
            ->where('round_number', 1)->where('sequence', 2)->first()->id
    );
    resolveMatch($wbR1M2, 0, 3);

    $wbFinal = freshMatch(
        CompetitionMatch::where('competition_stage_id', $stage->id)
            ->where('round_number', 2)->where('sequence', 1)->first()->id
    );
    resolveMatch($wbFinal, 3, 2);

    $lbR1 = freshMatch(
        CompetitionMatch::where('competition_stage_id', $stage->id)
            ->where('round_number', 101)->where('sequence', 1)->first()->id
    );
    resolveMatch($lbR1, 3, 0);

    $lbFinal = freshMatch(
        CompetitionMatch::where('competition_stage_id', $stage->id)
            ->where('round_number', 102)->where('sequence', 1)->first()->id
    );
    resolveMatch($lbFinal, 3, 2);

    // GF: WB champion (slot 1) wins → reset should be cancelled
    $gf = freshMatch(
        CompetitionMatch::where('competition_stage_id', $stage->id)
            ->where('round_number', 200)->where('sequence', 1)->first()->id
    );
    resolveMatch($gf, 3, 1);

    $resetMatch = CompetitionMatch::where('competition_stage_id', $stage->id)
        ->where('round_number', 202)
        ->first();

    expect($resetMatch->status)->toBe(MatchStatus::Cancelled)
        ->and($resetMatch->matchParticipants)->toHaveCount(0);
});

it('plays reset match when LB champion wins grand final', function () {
    $stage = createDEStageWithGFReset(4);

    (new Generator)->generate($stage);

    // Play through to GF
    $wbR1M1 = freshMatch(
        CompetitionMatch::where('competition_stage_id', $stage->id)
            ->where('round_number', 1)->where('sequence', 1)->first()->id
    );
    resolveMatch($wbR1M1, 3, 1);

    $wbR1M2 = freshMatch(
        CompetitionMatch::where('competition_stage_id', $stage->id)
            ->where('round_number', 1)->where('sequence', 2)->first()->id
    );
    resolveMatch($wbR1M2, 0, 3);

    $wbFinal = freshMatch(
        CompetitionMatch::where('competition_stage_id', $stage->id)
            ->where('round_number', 2)->where('sequence', 1)->first()->id
    );
    resolveMatch($wbFinal, 3, 2);

    $lbR1 = freshMatch(
        CompetitionMatch::where('competition_stage_id', $stage->id)
            ->where('round_number', 101)->where('sequence', 1)->first()->id
    );
    resolveMatch($lbR1, 3, 0);

    $lbFinal = freshMatch(
        CompetitionMatch::where('competition_stage_id', $stage->id)
            ->where('round_number', 102)->where('sequence', 1)->first()->id
    );
    resolveMatch($lbFinal, 3, 2);

    // GF: LB champion (slot 2) wins → reset should be played
    $gf = freshMatch(
        CompetitionMatch::where('competition_stage_id', $stage->id)
            ->where('round_number', 200)->where('sequence', 1)->first()->id
    );
    resolveMatch($gf, 1, 3);

    $resetMatch = freshMatch(
        CompetitionMatch::where('competition_stage_id', $stage->id)
            ->where('round_number', 202)->where('sequence', 1)->first()->id
    );

    expect($resetMatch->status)->toBe(MatchStatus::Pending)
        ->and($resetMatch->matchParticipants)->toHaveCount(2);

    // Play the reset match
    resolveMatch($resetMatch, 3, 2);

    $resetMatch->refresh();
    expect($resetMatch->status)->toBe(MatchStatus::Finished)
        ->and($resetMatch->winner_participant_id)->not->toBeNull();
});
