<?php

use App\Domain\Competition\Formats\SingleElimination\Generator;
use App\Domain\Competition\Formats\SingleElimination\Resolver;
use App\Domain\Competition\Formats\SingleElimination\Ruleset;
use App\Domain\Competition\Services\FormatRegistry;
use App\Enums\MatchResult;
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

function createStageWithParticipants(int $count): CompetitionStage
{
    $competition = Competition::factory()->tournament()->create();

    $stage = CompetitionStage::factory()->singleElimination()->create([
        'competition_id' => $competition->id,
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

// ─── Generator Tests ───

it('rejects fewer than 2 participants', function () {
    $stage = createStageWithParticipants(1);

    (new Generator)->generate($stage);
})->throws(InvalidArgumentException::class, 'at least 2 participants');

it('generates a bracket for 2 participants', function () {
    $stage = createStageWithParticipants(2);

    (new Generator)->generate($stage);

    $matches = CompetitionMatch::where('competition_stage_id', $stage->id)->get();

    expect($matches)->toHaveCount(1)
        ->and($matches->first()->round_number)->toBe(1)
        ->and($matches->first()->matchParticipants)->toHaveCount(2);
});

it('generates a bracket for 4 participants (power-of-2)', function () {
    $stage = createStageWithParticipants(4);

    (new Generator)->generate($stage);

    $matches = CompetitionMatch::where('competition_stage_id', $stage->id)->get();
    $connections = MatchConnection::whereIn('source_match_id', $matches->pluck('id'))->get();

    // 4p → 2 rounds: 2 + 1 = 3 matches
    expect($matches)->toHaveCount(3)
        ->and($matches->where('round_number', 1))->toHaveCount(2)
        ->and($matches->where('round_number', 2))->toHaveCount(1)
        ->and($connections)->toHaveCount(2);
});

it('generates a bracket for 8 participants', function () {
    $stage = createStageWithParticipants(8);

    (new Generator)->generate($stage);

    $matches = CompetitionMatch::where('competition_stage_id', $stage->id)->get();

    // 8p → 3 rounds: 4 + 2 + 1 = 7 matches
    expect($matches)->toHaveCount(7)
        ->and($matches->where('round_number', 1))->toHaveCount(4)
        ->and($matches->where('round_number', 2))->toHaveCount(2)
        ->and($matches->where('round_number', 3))->toHaveCount(1);
});

it('handles BYEs for non-power-of-2 participant counts', function () {
    $stage = createStageWithParticipants(3);

    (new Generator)->generate($stage);

    $matches = CompetitionMatch::where('competition_stage_id', $stage->id)->get();

    // 3p → bracket size 4 → 2 + 1 = 3 matches
    expect($matches)->toHaveCount(3);

    // One R1 match should be a BYE (finished, with winner)
    $byeMatches = $matches
        ->where('round_number', 1)
        ->where('status', MatchStatus::Finished);

    expect($byeMatches)->toHaveCount(1)
        ->and($byeMatches->first()->winner_participant_id)->not->toBeNull();
});

it('handles BYEs for 5 participants', function () {
    $stage = createStageWithParticipants(5);

    (new Generator)->generate($stage);

    $matches = CompetitionMatch::where('competition_stage_id', $stage->id)->get();

    // 5p → bracket size 8 → 4 + 2 + 1 = 7 matches
    expect($matches)->toHaveCount(7);

    // 3 BYEs (positions 6, 7, 8 are empty)
    $byeMatches = $matches
        ->where('round_number', 1)
        ->where('status', MatchStatus::Finished);

    expect($byeMatches)->toHaveCount(3);
});

it('seeds participants so top seeds are separated in bracket', function () {
    $stage = createStageWithParticipants(4);

    (new Generator)->generate($stage);

    $r1Matches = CompetitionMatch::where('competition_stage_id', $stage->id)
        ->where('round_number', 1)
        ->orderBy('sequence')
        ->get();

    $participants = $stage->competition->participants()->orderBy('seed')->get();

    // Seed 1 and Seed 2 should be in different R1 matches
    $seed1Match = $r1Matches->first(function ($match) use ($participants) {
        return $match->matchParticipants->contains('competition_participant_id', $participants[0]->id);
    });

    $seed2Match = $r1Matches->first(function ($match) use ($participants) {
        return $match->matchParticipants->contains('competition_participant_id', $participants[1]->id);
    });

    expect($seed1Match->id)->not->toBe($seed2Match->id);
});

it('wires match connections correctly for bracket progression', function () {
    $stage = createStageWithParticipants(4);

    (new Generator)->generate($stage);

    $final = CompetitionMatch::where('competition_stage_id', $stage->id)
        ->where('round_number', 2)
        ->first();

    $incomingConnections = MatchConnection::where('target_match_id', $final->id)->get();

    expect($incomingConnections)->toHaveCount(2)
        ->and($incomingConnections->pluck('source_outcome')->unique()->toArray())->toBe(['winner'])
        ->and($incomingConnections->pluck('target_slot')->sort()->values()->toArray())->toBe([1, 2]);
});

// ─── Resolver Tests ───

it('resolves a match and determines winner/loser', function () {
    $stage = createStageWithParticipants(2);

    (new Generator)->generate($stage);

    $match = CompetitionMatch::where('competition_stage_id', $stage->id)->first();
    $p1 = $match->matchParticipants->firstWhere('slot', 1);
    $p2 = $match->matchParticipants->firstWhere('slot', 2);

    $p1->update(['score' => 3]);
    $p2->update(['score' => 1]);

    (new Resolver)->resolve($match);

    $match->refresh();

    expect($match->status)->toBe(MatchStatus::Finished)
        ->and($match->winner_participant_id)->toBe($p1->competition_participant_id)
        ->and($match->loser_participant_id)->toBe($p2->competition_participant_id)
        ->and($match->finished_at)->not->toBeNull();
});

it('sets win/loss results on match participants', function () {
    $stage = createStageWithParticipants(2);

    (new Generator)->generate($stage);

    $match = CompetitionMatch::where('competition_stage_id', $stage->id)->first();
    $mp1 = $match->matchParticipants->firstWhere('slot', 1);
    $mp2 = $match->matchParticipants->firstWhere('slot', 2);

    $mp1->update(['score' => 2]);
    $mp2->update(['score' => 5]);

    (new Resolver)->resolve($match);

    expect($mp1->fresh()->result)->toBe(MatchResult::Loss)
        ->and($mp2->fresh()->result)->toBe(MatchResult::Win);
});

it('advances the winner to the next round via connections', function () {
    $stage = createStageWithParticipants(4);

    (new Generator)->generate($stage);

    $r1Match = CompetitionMatch::where('competition_stage_id', $stage->id)
        ->where('round_number', 1)
        ->where('sequence', 1)
        ->first();

    $mp1 = $r1Match->matchParticipants->firstWhere('slot', 1);
    $mp2 = $r1Match->matchParticipants->firstWhere('slot', 2);

    $mp1->update(['score' => 3]);
    $mp2->update(['score' => 0]);

    (new Resolver)->resolve($r1Match);

    $final = CompetitionMatch::where('competition_stage_id', $stage->id)
        ->where('round_number', 2)
        ->first();

    $finalParticipants = $final->matchParticipants()->get();

    expect($finalParticipants)->toHaveCount(1)
        ->and($finalParticipants->first()->competition_participant_id)->toBe($mp1->competition_participant_id)
        ->and($finalParticipants->first()->slot)->toBe(1);
});

it('rejects resolution when scores are tied', function () {
    $stage = createStageWithParticipants(2);

    (new Generator)->generate($stage);

    $match = CompetitionMatch::where('competition_stage_id', $stage->id)->first();
    $match->matchParticipants->firstWhere('slot', 1)->update(['score' => 2]);
    $match->matchParticipants->firstWhere('slot', 2)->update(['score' => 2]);

    (new Resolver)->resolve($match);
})->throws(InvalidArgumentException::class, 'tied');

it('rejects resolution when scores are missing', function () {
    $stage = createStageWithParticipants(2);

    (new Generator)->generate($stage);

    $match = CompetitionMatch::where('competition_stage_id', $stage->id)->first();

    (new Resolver)->resolve($match);
})->throws(InvalidArgumentException::class, 'scores are not set');

it('plays through a full 4-team single elimination tournament', function () {
    $stage = createStageWithParticipants(4);

    (new Generator)->generate($stage);

    // Play R1 Match 1: seed 1 beats seed 4
    $r1m1 = CompetitionMatch::where('competition_stage_id', $stage->id)
        ->where('round_number', 1)->where('sequence', 1)->first();
    $r1m1->matchParticipants->firstWhere('slot', 1)->update(['score' => 3]);
    $r1m1->matchParticipants->firstWhere('slot', 2)->update(['score' => 0]);
    (new Resolver)->resolve($r1m1);

    // Play R1 Match 2: seed 3 beats seed 2
    $r1m2 = CompetitionMatch::where('competition_stage_id', $stage->id)
        ->where('round_number', 1)->where('sequence', 2)->first();
    $r1m2->matchParticipants->firstWhere('slot', 1)->update(['score' => 1]);
    $r1m2->matchParticipants->firstWhere('slot', 2)->update(['score' => 2]);
    (new Resolver)->resolve($r1m2);

    // Final should now have 2 participants
    $final = CompetitionMatch::where('competition_stage_id', $stage->id)
        ->where('round_number', 2)->where('sequence', 1)->first();

    expect($final->matchParticipants)->toHaveCount(2);

    // Play Final
    $final->matchParticipants->firstWhere('slot', 1)->update(['score' => 3]);
    $final->matchParticipants->firstWhere('slot', 2)->update(['score' => 2]);
    (new Resolver)->resolve($final);

    $final->refresh();

    expect($final->status)->toBe(MatchStatus::Finished)
        ->and($final->winner_participant_id)->not->toBeNull();
});

// ─── Ruleset Tests ───

it('provides default settings', function () {
    $ruleset = new Ruleset;

    expect($ruleset->defaults())->toBe([
        'best_of' => 1,
        'third_place_match' => false,
    ]);
});

it('validates best_of must be odd', function () {
    $ruleset = new Ruleset;

    expect($ruleset->validate(['best_of' => 3]))->toBeEmpty()
        ->and($ruleset->validate(['best_of' => 2]))->toHaveKey('best_of');
});

it('applies defaults to a stage', function () {
    $stage = createStageWithParticipants(2);
    $stage->update(['settings' => null]);

    (new Ruleset)->apply($stage);

    expect($stage->fresh()->settings)->toBe([
        'best_of' => 1,
        'third_place_match' => false,
    ]);
});

// ─── FormatRegistry Integration ───

it('resolves single elimination via format registry', function () {
    $registry = new FormatRegistry;

    expect($registry->hasFormat(StageType::SingleElimination))->toBeTrue()
        ->and($registry->generator(StageType::SingleElimination))->toBeInstanceOf(Generator::class)
        ->and($registry->resolver(StageType::SingleElimination))->toBeInstanceOf(Resolver::class)
        ->and($registry->ruleset(StageType::SingleElimination))->toBeInstanceOf(Ruleset::class);
});

// ─── Third Place Match Tests ───

function createStageWithThirdPlace(int $count): CompetitionStage
{
    $competition = Competition::factory()->tournament()->create();

    $stage = CompetitionStage::factory()->singleElimination()->create([
        'competition_id' => $competition->id,
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

it('generates a 3rd place match for 4 participants when enabled', function () {
    $stage = createStageWithThirdPlace(4);

    (new Generator)->generate($stage);

    $matches = CompetitionMatch::where('competition_stage_id', $stage->id)->get();

    // 4p → 2 + 1 regular + 1 third place = 4 matches
    expect($matches)->toHaveCount(4);

    $thirdPlaceMatch = $matches->first(fn ($m) => $m->settings['third_place'] ?? false);
    expect($thirdPlaceMatch)->not->toBeNull()
        ->and($thirdPlaceMatch->round_number)->toBe(2)
        ->and($thirdPlaceMatch->sequence)->toBe(2);

    // Should have loser connections from both semifinal matches
    $incoming = MatchConnection::where('target_match_id', $thirdPlaceMatch->id)->get();
    expect($incoming)->toHaveCount(2)
        ->and($incoming->pluck('source_outcome')->unique()->toArray())->toBe(['loser'])
        ->and($incoming->pluck('target_slot')->sort()->values()->toArray())->toBe([1, 2]);
});

it('does not generate a 3rd place match when setting is disabled', function () {
    $stage = createStageWithParticipants(4);

    (new Generator)->generate($stage);

    $matches = CompetitionMatch::where('competition_stage_id', $stage->id)->get();

    expect($matches)->toHaveCount(3);

    $thirdPlaceMatch = $matches->first(fn ($m) => $m->settings['third_place'] ?? false);
    expect($thirdPlaceMatch)->toBeNull();
});

it('does not generate a 3rd place match for 2 participants', function () {
    $competition = Competition::factory()->tournament()->create();

    $stage = CompetitionStage::factory()->singleElimination()->create([
        'competition_id' => $competition->id,
        'order' => 1,
        'settings' => ['third_place_match' => true],
    ]);

    for ($i = 1; $i <= 2; $i++) {
        CompetitionParticipant::factory()->forTeam(Team::factory()->create())->create([
            'competition_id' => $competition->id,
            'seed' => $i,
        ]);
    }

    (new Generator)->generate($stage);

    $matches = CompetitionMatch::where('competition_stage_id', $stage->id)->get();

    // Only 1 match (the final), no 3rd place — only 1 round
    expect($matches)->toHaveCount(1);

    $thirdPlaceMatch = $matches->first(fn ($m) => $m->settings['third_place'] ?? false);
    expect($thirdPlaceMatch)->toBeNull();
});

it('routes semifinal losers to 3rd place match', function () {
    $stage = createStageWithThirdPlace(4);

    (new Generator)->generate($stage);

    // Play both semifinals
    $r1m1 = CompetitionMatch::where('competition_stage_id', $stage->id)
        ->where('round_number', 1)->where('sequence', 1)->first();
    $r1m1->matchParticipants->firstWhere('slot', 1)->update(['score' => 3]);
    $r1m1->matchParticipants->firstWhere('slot', 2)->update(['score' => 0]);
    (new Resolver)->resolve($r1m1);

    $r1m2 = CompetitionMatch::where('competition_stage_id', $stage->id)
        ->where('round_number', 1)->where('sequence', 2)->first();
    $r1m2->matchParticipants->firstWhere('slot', 1)->update(['score' => 1]);
    $r1m2->matchParticipants->firstWhere('slot', 2)->update(['score' => 2]);
    (new Resolver)->resolve($r1m2);

    // 3rd place match should now have 2 participants (the losers)
    $thirdPlaceMatch = CompetitionMatch::where('competition_stage_id', $stage->id)
        ->where('settings->third_place', true)
        ->first();

    expect($thirdPlaceMatch->matchParticipants)->toHaveCount(2);

    // The losers from the semifinals should be in the 3rd place match
    $loser1 = $r1m1->fresh()->loser_participant_id;
    $loser2 = $r1m2->fresh()->loser_participant_id;
    $tpParticipantIds = $thirdPlaceMatch->matchParticipants->pluck('competition_participant_id')->sort()->values()->toArray();

    expect($tpParticipantIds)->toBe(collect([$loser1, $loser2])->sort()->values()->toArray());
});

it('plays through a full 4-team SE tournament with 3rd place match', function () {
    $stage = createStageWithThirdPlace(4);

    (new Generator)->generate($stage);

    // Semifinal 1
    $r1m1 = CompetitionMatch::where('competition_stage_id', $stage->id)
        ->where('round_number', 1)->where('sequence', 1)->first();
    $r1m1->matchParticipants->firstWhere('slot', 1)->update(['score' => 3]);
    $r1m1->matchParticipants->firstWhere('slot', 2)->update(['score' => 0]);
    (new Resolver)->resolve($r1m1);

    // Semifinal 2
    $r1m2 = CompetitionMatch::where('competition_stage_id', $stage->id)
        ->where('round_number', 1)->where('sequence', 2)->first();
    $r1m2->matchParticipants->firstWhere('slot', 1)->update(['score' => 1]);
    $r1m2->matchParticipants->firstWhere('slot', 2)->update(['score' => 2]);
    (new Resolver)->resolve($r1m2);

    // Final
    $final = CompetitionMatch::where('competition_stage_id', $stage->id)
        ->where('round_number', 2)->where('sequence', 1)->first();
    expect($final->matchParticipants)->toHaveCount(2);
    $final->matchParticipants->firstWhere('slot', 1)->update(['score' => 3]);
    $final->matchParticipants->firstWhere('slot', 2)->update(['score' => 2]);
    (new Resolver)->resolve($final);

    // 3rd Place Match
    $thirdPlace = CompetitionMatch::where('competition_stage_id', $stage->id)
        ->where('settings->third_place', true)
        ->with('matchParticipants')
        ->first();
    expect($thirdPlace->matchParticipants)->toHaveCount(2);
    $thirdPlace->matchParticipants->firstWhere('slot', 1)->update(['score' => 2]);
    $thirdPlace->matchParticipants->firstWhere('slot', 2)->update(['score' => 1]);
    (new Resolver)->resolve($thirdPlace);

    $thirdPlace->refresh();
    expect($thirdPlace->status)->toBe(MatchStatus::Finished)
        ->and($thirdPlace->winner_participant_id)->not->toBeNull();

    // All 4 matches should be finished
    $allFinished = CompetitionMatch::where('competition_stage_id', $stage->id)
        ->where('status', MatchStatus::Finished)
        ->count();
    expect($allFinished)->toBe(4);
});
