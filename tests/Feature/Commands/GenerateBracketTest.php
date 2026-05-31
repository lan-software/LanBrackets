<?php

use App\Actions\AddCompetitionParticipantAction;
use App\Actions\CreateCompetitionAction;
use App\Enums\CompetitionType;
use App\Enums\StageStatus;
use App\Enums\StageType;
use App\Models\Competition;
use App\Models\Team;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Generating a bracket fires BracketGenerated, which the webhook listener
    // turns into outbound HTTP calls. Keep the test hermetic.
    Http::fake();
});

function makeBracketCompetition(int $participants, StageType $stageType = StageType::SingleElimination): Competition
{
    $competition = app(CreateCompetitionAction::class)->execute(
        name: 'Bracket Cup '.uniqid(),
        type: CompetitionType::Tournament,
        stageType: $stageType,
    );

    $addParticipant = app(AddCompetitionParticipantAction::class);

    for ($i = 1; $i <= $participants; $i++) {
        $addParticipant->execute($competition, Team::factory()->create(), $i);
    }

    return $competition->fresh(['stages', 'participants']);
}

it('generates a bracket for a competition with enough participants', function () {
    $competition = makeBracketCompetition(4);

    $this->artisan('competition:generate-bracket', ['competition' => $competition->id])
        ->expectsOutputToContain("Competition: {$competition->name}")
        ->expectsOutputToContain('Bracket generated:')
        ->assertExitCode(0);

    expect($competition->matches()->count())->toBeGreaterThan(0);
    expect($competition->stages()->first()->status)->toBe(StageStatus::Running);
});

it('fails when the competition does not exist', function () {
    $this->artisan('competition:generate-bracket', ['competition' => '01999999999999999999999999'])
        ->expectsOutputToContain('Competition not found.')
        ->assertExitCode(1);
});

it('fails when there are not enough participants', function () {
    $competition = makeBracketCompetition(1);

    $this->artisan('competition:generate-bracket', ['competition' => $competition->id])
        ->expectsOutputToContain('Stage requires at least 2 participants')
        ->assertExitCode(1);

    expect($competition->matches()->count())->toBe(0);
});
