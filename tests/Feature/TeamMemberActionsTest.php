<?php

use App\Actions\AddTeamMemberAction;
use App\Actions\AssignTeamCaptainAction;
use App\Actions\RemoveTeamMemberAction;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// --- AddTeamMemberAction ---

it('auto-assigns first member as captain', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();

    $member = (new AddTeamMemberAction)->execute($team, $user);

    expect($member->is_captain)->toBeTrue();
});

it('does not auto-assign captain when team already has one', function () {
    $team = Team::factory()->create();
    TeamMember::factory()->captain()->create(['team_id' => $team->id]);

    $user = User::factory()->create();
    $member = (new AddTeamMemberAction)->execute($team, $user);

    expect($member->is_captain)->toBeFalse();
});

it('prevents adding duplicate active members', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();

    (new AddTeamMemberAction)->execute($team, $user);
    (new AddTeamMemberAction)->execute($team, $user);
})->throws(InvalidArgumentException::class, 'already an active member');

// --- AssignTeamCaptainAction ---

it('assigns a member as captain', function () {
    $team = Team::factory()->create();
    $captain = TeamMember::factory()->captain()->create(['team_id' => $team->id]);
    $member = TeamMember::factory()->create(['team_id' => $team->id]);

    (new AssignTeamCaptainAction)->execute($member);

    expect($member->fresh()->is_captain)->toBeTrue()
        ->and($captain->fresh()->is_captain)->toBeFalse();
});

it('prevents assigning inactive member as captain', function () {
    $member = TeamMember::factory()->inactive()->create();

    (new AssignTeamCaptainAction)->execute($member);
})->throws(InvalidArgumentException::class, 'inactive member');

it('is idempotent when member is already captain', function () {
    $captain = TeamMember::factory()->captain()->create();

    (new AssignTeamCaptainAction)->execute($captain);

    expect($captain->fresh()->is_captain)->toBeTrue();
});

// --- RemoveTeamMemberAction ---

it('removes a team member by setting left_at', function () {
    $team = Team::factory()->create();
    $captain = TeamMember::factory()->captain()->create(['team_id' => $team->id]);
    $member = TeamMember::factory()->create(['team_id' => $team->id]);

    (new RemoveTeamMemberAction)->execute($member);

    expect($member->fresh()->left_at)->not->toBeNull();
});

it('prevents removing the last captain without reassignment', function () {
    $team = Team::factory()->create();
    $captain = TeamMember::factory()->captain()->create(['team_id' => $team->id]);
    TeamMember::factory()->create(['team_id' => $team->id]);

    (new RemoveTeamMemberAction)->execute($captain);
})->throws(InvalidArgumentException::class, 'Assign a new captain first');

it('prevents removing the last team member', function () {
    $team = Team::factory()->create();
    $captain = TeamMember::factory()->captain()->create(['team_id' => $team->id]);

    (new RemoveTeamMemberAction)->execute($captain);
})->throws(InvalidArgumentException::class, 'last member');

it('allows removing a captain when another captain exists', function () {
    $team = Team::factory()->create();
    $captain1 = TeamMember::factory()->captain()->create(['team_id' => $team->id]);
    $captain2 = TeamMember::factory()->captain()->create(['team_id' => $team->id]);

    (new RemoveTeamMemberAction)->execute($captain1);

    expect($captain1->fresh()->left_at)->not->toBeNull()
        ->and($captain2->fresh()->is_captain)->toBeTrue();
});
