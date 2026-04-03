<?php

use App\Domain\Competition\Formats\RoundRobin\Generator;
use App\Domain\Competition\Formats\RoundRobin\Resolver;
use App\Domain\Competition\Formats\RoundRobin\Ruleset;
use App\Domain\Competition\Formats\RoundRobin\Scheduler;
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

function createRRStageWithParticipants(int $count): CompetitionStage
{
    $competition = Competition::factory()->tournament()->create();

    $stage = CompetitionStage::factory()->roundRobin()->create([
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
    $stage = createRRStageWithParticipants(1);

    (new Generator(new Scheduler))->generate($stage);
})->throws(InvalidArgumentException::class, 'at least 2 participants');

it('generates correct matches for 2 participants', function () {
    $stage = createRRStageWithParticipants(2);

    app(Generator::class)->generate($stage);

    $matches = CompetitionMatch::where('competition_stage_id', $stage->id)->get();

    // 2p → 1 round, 1 match
    expect($matches)->toHaveCount(1)
        ->and($matches->first()->round_number)->toBe(1)
        ->and($matches->first()->matchParticipants)->toHaveCount(2);
});

it('generates correct matches for 4 participants', function () {
    $stage = createRRStageWithParticipants(4);

    app(Generator::class)->generate($stage);

    $matches = CompetitionMatch::where('competition_stage_id', $stage->id)->get();

    // 4p → 3 rounds, 2 matches per round = 6 matches total
    expect($matches)->toHaveCount(6)
        ->and($matches->where('round_number', 1))->toHaveCount(2)
        ->and($matches->where('round_number', 2))->toHaveCount(2)
        ->and($matches->where('round_number', 3))->toHaveCount(2);
});

it('generates correct matches for 6 participants', function () {
    $stage = createRRStageWithParticipants(6);

    app(Generator::class)->generate($stage);

    $matches = CompetitionMatch::where('competition_stage_id', $stage->id)->get();

    // 6p → 5 rounds, 3 matches per round = 15 matches total
    expect($matches)->toHaveCount(15);
});

it('every participant plays every other participant exactly once', function () {
    $stage = createRRStageWithParticipants(4);

    app(Generator::class)->generate($stage);

    $participants = $stage->competition->participants()->orderBy('seed')->get();
    $matches = CompetitionMatch::where('competition_stage_id', $stage->id)->get();

    // Check each pair of participants has exactly one match
    foreach ($participants as $i => $p1) {
        foreach ($participants as $j => $p2) {
            if ($i >= $j) {
                continue;
            }

            $sharedMatches = $matches->filter(function ($match) use ($p1, $p2) {
                $mpIds = $match->matchParticipants->pluck('competition_participant_id');

                return $mpIds->contains($p1->id) && $mpIds->contains($p2->id);
            });

            expect($sharedMatches)->toHaveCount(1);
        }
    }
});

it('handles BYEs for odd participant counts', function () {
    $stage = createRRStageWithParticipants(5);

    app(Generator::class)->generate($stage);

    $matches = CompetitionMatch::where('competition_stage_id', $stage->id)->get();

    // 5p → add virtual BYE → 6 participants → 5 rounds, 3 matches per round = 15
    // But 5 of those are BYE matches (each participant gets 1 BYE)
    expect($matches)->toHaveCount(15);

    $byeMatches = $matches->where('status', MatchStatus::Finished);
    expect($byeMatches)->toHaveCount(5);

    // Each BYE match should have exactly 1 real participant
    $byeMatches->each(function ($match) {
        expect($match->matchParticipants)->toHaveCount(1)
            ->and($match->winner_participant_id)->not->toBeNull();
    });
});

it('generates correct matches for 3 participants with BYEs', function () {
    $stage = createRRStageWithParticipants(3);

    app(Generator::class)->generate($stage);

    $matches = CompetitionMatch::where('competition_stage_id', $stage->id)->get();

    // 3p → add virtual BYE → 4 participants → 3 rounds, 2 matches per round = 6
    // 3 of those are BYE matches
    expect($matches)->toHaveCount(6);

    $realMatches = $matches->where('status', MatchStatus::Pending);
    $byeMatches = $matches->where('status', MatchStatus::Finished);

    expect($realMatches)->toHaveCount(3)
        ->and($byeMatches)->toHaveCount(3);
});

it('does not create match connections', function () {
    $stage = createRRStageWithParticipants(4);

    app(Generator::class)->generate($stage);

    $matches = CompetitionMatch::where('competition_stage_id', $stage->id)->get();

    $connections = MatchConnection::whereIn('source_match_id', $matches->pluck('id'))->count();

    expect($connections)->toBe(0);
});

// ─── Resolver Tests ───

it('resolves a match with a winner', function () {
    $stage = createRRStageWithParticipants(4);

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
        ->and($match->loser_participant_id)->toBe($p2->competition_participant_id);
});

it('supports draws when scores are tied', function () {
    $stage = createRRStageWithParticipants(4);
    $stage->update(['settings' => ['allow_draws' => true]]);

    app(Generator::class)->generate($stage);

    $match = CompetitionMatch::where('competition_stage_id', $stage->id)
        ->where('status', MatchStatus::Pending)
        ->first();

    $p1 = $match->matchParticipants->firstWhere('slot', 1);
    $p2 = $match->matchParticipants->firstWhere('slot', 2);

    $p1->update(['score' => 2]);
    $p2->update(['score' => 2]);

    (new Resolver)->resolve($match);

    $match->refresh();

    expect($match->status)->toBe(MatchStatus::Finished)
        ->and($match->winner_participant_id)->toBeNull()
        ->and($p1->fresh()->result)->toBe(MatchResult::Draw)
        ->and($p2->fresh()->result)->toBe(MatchResult::Draw);
});

it('rejects draws when allow_draws is false', function () {
    $stage = createRRStageWithParticipants(4);
    $stage->update(['settings' => ['allow_draws' => false]]);

    app(Generator::class)->generate($stage);

    $match = CompetitionMatch::where('competition_stage_id', $stage->id)
        ->where('status', MatchStatus::Pending)
        ->first();

    $match->matchParticipants->firstWhere('slot', 1)->update(['score' => 2]);
    $match->matchParticipants->firstWhere('slot', 2)->update(['score' => 2]);

    (new Resolver)->resolve($match);
})->throws(InvalidArgumentException::class, 'draws are not allowed');

it('rejects resolution when scores are missing', function () {
    $stage = createRRStageWithParticipants(4);

    app(Generator::class)->generate($stage);

    $match = CompetitionMatch::where('competition_stage_id', $stage->id)
        ->where('status', MatchStatus::Pending)
        ->first();

    (new Resolver)->resolve($match);
})->throws(InvalidArgumentException::class, 'scores are not set');

it('plays through a full 4-team round robin', function () {
    $stage = createRRStageWithParticipants(4);

    app(Generator::class)->generate($stage);

    $matches = CompetitionMatch::where('competition_stage_id', $stage->id)
        ->where('status', MatchStatus::Pending)
        ->get();

    expect($matches)->toHaveCount(6);

    foreach ($matches as $match) {
        $match->matchParticipants->firstWhere('slot', 1)->update(['score' => 3]);
        $match->matchParticipants->firstWhere('slot', 2)->update(['score' => 1]);
        (new Resolver)->resolve($match);
    }

    $allFinished = CompetitionMatch::where('competition_stage_id', $stage->id)
        ->where('status', MatchStatus::Finished)
        ->count();

    expect($allFinished)->toBe(6);
});

// ─── Ruleset Tests ───

it('provides default settings', function () {
    $ruleset = new Ruleset;

    expect($ruleset->defaults())->toBe([
        'best_of' => 1,
        'points_win' => 3,
        'points_draw' => 1,
        'points_loss' => 0,
        'allow_draws' => true,
    ]);
});

it('validates best_of must be odd', function () {
    $ruleset = new Ruleset;

    expect($ruleset->validate(['best_of' => 3]))->toBeEmpty()
        ->and($ruleset->validate(['best_of' => 2]))->toHaveKey('best_of');
});

it('validates points must be non-negative integers', function () {
    $ruleset = new Ruleset;

    expect($ruleset->validate(['points_win' => -1]))->toHaveKey('points_win')
        ->and($ruleset->validate(['points_win' => 5]))->toBeEmpty();
});

it('applies defaults to a stage', function () {
    $stage = createRRStageWithParticipants(2);
    $stage->update(['settings' => null]);

    (new Ruleset)->apply($stage);

    expect($stage->fresh()->settings)->toBe([
        'best_of' => 1,
        'points_win' => 3,
        'points_draw' => 1,
        'points_loss' => 0,
        'allow_draws' => true,
    ]);
});

// ─── FormatRegistry Integration ───

it('resolves round robin via format registry', function () {
    $registry = new FormatRegistry;

    expect($registry->hasFormat(StageType::RoundRobin))->toBeTrue()
        ->and($registry->generator(StageType::RoundRobin))->toBeInstanceOf(Generator::class)
        ->and($registry->resolver(StageType::RoundRobin))->toBeInstanceOf(Resolver::class)
        ->and($registry->ruleset(StageType::RoundRobin))->toBeInstanceOf(Ruleset::class);
});
