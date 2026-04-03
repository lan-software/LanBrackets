<?php

use App\Domain\Competition\Formats\GroupStage\Generator;
use App\Domain\Competition\Formats\GroupStage\Resolver;
use App\Domain\Competition\Formats\GroupStage\Ruleset;
use App\Domain\Competition\Services\FormatRegistry;
use App\Enums\MatchResult;
use App\Enums\MatchStatus;
use App\Enums\StageType;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\CompetitionParticipant;
use App\Models\CompetitionStage;
use App\Models\CompetitionStageGroup;
use App\Models\CompetitionStageGroupMember;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helper ───

function createGSStageWithParticipants(int $count, ?int $groupCount = null): CompetitionStage
{
    $competition = Competition::factory()->tournament()->create();

    $settings = $groupCount !== null ? ['group_count' => $groupCount] : null;

    $stage = CompetitionStage::factory()->groupStage()->create([
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

// ─── Generator Tests ───

it('rejects fewer than 4 participants', function () {
    $stage = createGSStageWithParticipants(3);

    app(Generator::class)->generate($stage);
})->throws(InvalidArgumentException::class, 'at least 4 participants');

it('creates correct number of groups for 8 participants', function () {
    $stage = createGSStageWithParticipants(8, groupCount: 2);

    app(Generator::class)->generate($stage);

    $groups = CompetitionStageGroup::where('competition_stage_id', $stage->id)->get();

    expect($groups)->toHaveCount(2)
        ->and($groups[0]->name)->toBe('Group A')
        ->and($groups[1]->name)->toBe('Group B');
});

it('auto-calculates group count based on group_size', function () {
    $stage = createGSStageWithParticipants(8);

    app(Generator::class)->generate($stage);

    // Default group_size=4, so 8/4 = 2 groups
    $groups = CompetitionStageGroup::where('competition_stage_id', $stage->id)->get();

    expect($groups)->toHaveCount(2);
});

it('distributes participants via serpentine seeding', function () {
    $stage = createGSStageWithParticipants(8, groupCount: 2);

    app(Generator::class)->generate($stage);

    $participants = $stage->competition->participants()->orderBy('seed')->get();
    $groups = CompetitionStageGroup::where('competition_stage_id', $stage->id)
        ->orderBy('sequence')
        ->get();

    $groupAMembers = CompetitionStageGroupMember::where('competition_stage_group_id', $groups[0]->id)
        ->pluck('competition_participant_id');
    $groupBMembers = CompetitionStageGroupMember::where('competition_stage_group_id', $groups[1]->id)
        ->pluck('competition_participant_id');

    // Serpentine: Group A gets seeds 1,4,5,8 and Group B gets 2,3,6,7
    expect($groupAMembers)->toContain($participants[0]->id) // seed 1
        ->and($groupAMembers)->toContain($participants[3]->id) // seed 4
        ->and($groupBMembers)->toContain($participants[1]->id) // seed 2
        ->and($groupBMembers)->toContain($participants[2]->id); // seed 3
});

it('creates group member records', function () {
    $stage = createGSStageWithParticipants(8, groupCount: 2);

    app(Generator::class)->generate($stage);

    $totalMembers = CompetitionStageGroupMember::query()
        ->whereHas('group', fn ($q) => $q->where('competition_stage_id', $stage->id))
        ->count();

    expect($totalMembers)->toBe(8);
});

it('creates correct number of matches per group', function () {
    $stage = createGSStageWithParticipants(8, groupCount: 2);

    app(Generator::class)->generate($stage);

    $matches = CompetitionMatch::where('competition_stage_id', $stage->id)->get();

    // 2 groups of 4: each group has C(4,2)=6 matches → 12 total
    expect($matches)->toHaveCount(12);

    $groups = CompetitionStageGroup::where('competition_stage_id', $stage->id)->get();

    foreach ($groups as $group) {
        $groupMatches = $matches->filter(fn ($m) => ($m->settings['group_id'] ?? null) === $group->id);
        expect($groupMatches)->toHaveCount(6);
    }
});

it('ensures participants only play others in their group', function () {
    $stage = createGSStageWithParticipants(8, groupCount: 2);

    app(Generator::class)->generate($stage);

    $groups = CompetitionStageGroup::where('competition_stage_id', $stage->id)->get();

    foreach ($groups as $group) {
        $memberIds = CompetitionStageGroupMember::where('competition_stage_group_id', $group->id)
            ->pluck('competition_participant_id')
            ->toArray();

        $groupMatches = CompetitionMatch::where('competition_stage_id', $stage->id)
            ->where('settings->group_id', $group->id)
            ->get();

        foreach ($groupMatches as $match) {
            $matchParticipantIds = $match->matchParticipants
                ->pluck('competition_participant_id')
                ->toArray();

            foreach ($matchParticipantIds as $id) {
                expect($memberIds)->toContain($id);
            }
        }
    }
});

it('tags matches with group_id in settings', function () {
    $stage = createGSStageWithParticipants(8, groupCount: 2);

    app(Generator::class)->generate($stage);

    $matches = CompetitionMatch::where('competition_stage_id', $stage->id)->get();

    $matches->each(function ($match) {
        expect($match->settings)->toHaveKey('group_id');
    });
});

// ─── Resolver Tests ───

it('resolves a match with a winner', function () {
    $stage = createGSStageWithParticipants(8, groupCount: 2);

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
        ->and($match->winner_participant_id)->toBe($p1->competition_participant_id);
});

it('supports draws within groups', function () {
    $stage = createGSStageWithParticipants(8, groupCount: 2);
    $stage->update(['settings' => array_merge($stage->settings ?? [], ['allow_draws' => true])]);

    app(Generator::class)->generate($stage);

    $match = CompetitionMatch::where('competition_stage_id', $stage->id)
        ->where('status', MatchStatus::Pending)
        ->first();

    $p1 = $match->matchParticipants->firstWhere('slot', 1);
    $p2 = $match->matchParticipants->firstWhere('slot', 2);

    $p1->update(['score' => 2]);
    $p2->update(['score' => 2]);

    (new Resolver)->resolve($match);

    expect($p1->fresh()->result)->toBe(MatchResult::Draw)
        ->and($p2->fresh()->result)->toBe(MatchResult::Draw);
});

it('plays through a full group stage with 8 participants in 2 groups', function () {
    $stage = createGSStageWithParticipants(8, groupCount: 2);

    app(Generator::class)->generate($stage);

    $matches = CompetitionMatch::where('competition_stage_id', $stage->id)
        ->where('status', MatchStatus::Pending)
        ->get();

    foreach ($matches as $match) {
        $match->matchParticipants->firstWhere('slot', 1)->update(['score' => 3]);
        $match->matchParticipants->firstWhere('slot', 2)->update(['score' => 1]);
        (new Resolver)->resolve($match);
    }

    $allFinished = CompetitionMatch::where('competition_stage_id', $stage->id)
        ->where('status', MatchStatus::Finished)
        ->count();

    expect($allFinished)->toBe(12);
});

// ─── Ruleset Tests ───

it('provides default settings', function () {
    $ruleset = new Ruleset;

    expect($ruleset->defaults())->toBe([
        'best_of' => 1,
        'group_count' => null,
        'group_size' => 4,
        'points_win' => 3,
        'points_draw' => 1,
        'points_loss' => 0,
        'allow_draws' => true,
        'advance_count' => 2,
    ]);
});

it('validates group_count must be at least 2', function () {
    $ruleset = new Ruleset;

    expect($ruleset->validate(['group_count' => 2]))->toBeEmpty()
        ->and($ruleset->validate(['group_count' => 1]))->toHaveKey('group_count')
        ->and($ruleset->validate(['group_count' => null]))->toBeEmpty();
});

it('validates group_size must be at least 2', function () {
    $ruleset = new Ruleset;

    expect($ruleset->validate(['group_size' => 3]))->toBeEmpty()
        ->and($ruleset->validate(['group_size' => 1]))->toHaveKey('group_size');
});

it('applies defaults to a stage', function () {
    $stage = createGSStageWithParticipants(4);
    $stage->update(['settings' => null]);

    (new Ruleset)->apply($stage);

    expect($stage->fresh()->settings)->toBe([
        'best_of' => 1,
        'group_count' => null,
        'group_size' => 4,
        'points_win' => 3,
        'points_draw' => 1,
        'points_loss' => 0,
        'allow_draws' => true,
        'advance_count' => 2,
    ]);
});

// ─── FormatRegistry Integration ───

it('resolves group stage via format registry', function () {
    $registry = new FormatRegistry;

    expect($registry->hasFormat(StageType::GroupStage))->toBeTrue()
        ->and($registry->generator(StageType::GroupStage))->toBeInstanceOf(Generator::class)
        ->and($registry->resolver(StageType::GroupStage))->toBeInstanceOf(Resolver::class)
        ->and($registry->ruleset(StageType::GroupStage))->toBeInstanceOf(Ruleset::class);
});
