<?php

use App\Enums\CompetitionStatus;
use App\Enums\CompetitionType;
use App\Enums\MatchStatus;
use App\Enums\StageType;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\CompetitionParticipant;
use App\Models\CompetitionStage;
use App\Models\CompetitionStageGroup;
use App\Models\CompetitionStageGroupMember;
use App\Models\MatchConnection;
use App\Models\MatchParticipant;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// --- Competition → Stages → Matches relationship chain ---

it('creates a competition with stages and matches', function () {
    $competition = Competition::factory()->tournament()->create();

    $stage = CompetitionStage::factory()->singleElimination()->create([
        'competition_id' => $competition->id,
        'name' => 'Playoffs',
        'order' => 1,
    ]);

    $match = CompetitionMatch::factory()->create([
        'competition_id' => $competition->id,
        'competition_stage_id' => $stage->id,
        'round_number' => 1,
        'sequence' => 1,
    ]);

    expect($competition->stages)->toHaveCount(1)
        ->and($competition->matches)->toHaveCount(1)
        ->and($stage->matches)->toHaveCount(1)
        ->and($match->stage->id)->toBe($stage->id)
        ->and($match->competition->id)->toBe($competition->id);
});

// --- Polymorphic participant support ---

it('supports team-based participants', function () {
    $competition = Competition::factory()->tournament()->create();
    $team = Team::factory()->create();

    $participant = CompetitionParticipant::factory()->forTeam($team)->create([
        'competition_id' => $competition->id,
    ]);

    expect($participant->participant)->toBeInstanceOf(Team::class)
        ->and($participant->participant->id)->toBe($team->id)
        ->and($competition->participants)->toHaveCount(1);
});

it('supports user-based participants', function () {
    $competition = Competition::factory()->create();
    $user = User::factory()->create();

    $participant = CompetitionParticipant::factory()->forUser($user)->create([
        'competition_id' => $competition->id,
    ]);

    expect($participant->participant)->toBeInstanceOf(User::class)
        ->and($participant->participant->id)->toBe($user->id);
});

// --- Match connections (bracket graph) ---

it('creates match connections for bracket progression', function () {
    $competition = Competition::factory()->tournament()->create();

    $semi1 = CompetitionMatch::factory()->create([
        'competition_id' => $competition->id,
        'round_number' => 1,
        'sequence' => 1,
    ]);

    $semi2 = CompetitionMatch::factory()->create([
        'competition_id' => $competition->id,
        'round_number' => 1,
        'sequence' => 2,
    ]);

    $final = CompetitionMatch::factory()->create([
        'competition_id' => $competition->id,
        'round_number' => 2,
        'sequence' => 1,
    ]);

    MatchConnection::factory()->create([
        'source_match_id' => $semi1->id,
        'source_outcome' => 'winner',
        'target_match_id' => $final->id,
        'target_slot' => 1,
    ]);

    MatchConnection::factory()->create([
        'source_match_id' => $semi2->id,
        'source_outcome' => 'winner',
        'target_match_id' => $final->id,
        'target_slot' => 2,
    ]);

    expect($semi1->outgoingConnections)->toHaveCount(1)
        ->and($semi2->outgoingConnections)->toHaveCount(1)
        ->and($final->incomingConnections)->toHaveCount(2);
});

// --- Match participants ---

it('assigns participants to match slots', function () {
    $competition = Competition::factory()->tournament()->create();

    $team1 = Team::factory()->create();
    $team2 = Team::factory()->create();

    $p1 = CompetitionParticipant::factory()->forTeam($team1)->create([
        'competition_id' => $competition->id,
    ]);
    $p2 = CompetitionParticipant::factory()->forTeam($team2)->create([
        'competition_id' => $competition->id,
    ]);

    $match = CompetitionMatch::factory()->create([
        'competition_id' => $competition->id,
    ]);

    MatchParticipant::factory()->create([
        'match_id' => $match->id,
        'competition_participant_id' => $p1->id,
        'slot' => 1,
    ]);

    MatchParticipant::factory()->create([
        'match_id' => $match->id,
        'competition_participant_id' => $p2->id,
        'slot' => 2,
    ]);

    expect($match->matchParticipants)->toHaveCount(2);
});

// --- Group stage support ---

it('creates groups with members for a group stage', function () {
    $competition = Competition::factory()->tournament()->create();

    $stage = CompetitionStage::factory()->groupStage()->create([
        'competition_id' => $competition->id,
    ]);

    $group = CompetitionStageGroup::factory()->create([
        'competition_stage_id' => $stage->id,
        'name' => 'Group A',
    ]);

    $teams = Team::factory()->count(4)->create();
    $participants = $teams->map(fn (Team $team) => CompetitionParticipant::factory()->forTeam($team)->create([
        'competition_id' => $competition->id,
    ]));

    $participants->each(fn (CompetitionParticipant $p) => CompetitionStageGroupMember::factory()->create([
        'competition_stage_group_id' => $group->id,
        'competition_participant_id' => $p->id,
    ]));

    expect($stage->groups)->toHaveCount(1)
        ->and($group->members)->toHaveCount(4);
});

// --- Competition enums & visibility ---

it('correctly identifies publicly visible competitions', function () {
    $publicCompetition = Competition::factory()->published()->create();
    $privateCompetition = Competition::factory()->create();

    expect($publicCompetition->isPubliclyVisible())->toBeTrue()
        ->and($privateCompetition->isPubliclyVisible())->toBeFalse();
});

it('supports all competition types', function () {
    expect(CompetitionType::cases())->toHaveCount(3);
});

it('supports all competition statuses', function () {
    expect(CompetitionStatus::cases())->toHaveCount(8);
});

// --- Multi-stage competition ---

it('supports multi-stage competitions', function () {
    $competition = Competition::factory()->tournament()->create();

    CompetitionStage::factory()->groupStage()->create([
        'competition_id' => $competition->id,
        'name' => 'Group Stage',
        'order' => 1,
    ]);

    CompetitionStage::factory()->singleElimination()->create([
        'competition_id' => $competition->id,
        'name' => 'Playoffs',
        'order' => 2,
    ]);

    $stages = $competition->stages;

    expect($stages)->toHaveCount(2)
        ->and($stages->first()->stage_type)->toBe(StageType::GroupStage)
        ->and($stages->last()->stage_type)->toBe(StageType::SingleElimination);
});
