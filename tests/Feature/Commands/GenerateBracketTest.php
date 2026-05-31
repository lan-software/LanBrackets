<?php

use App\Actions\AddCompetitionParticipantAction;
use App\Actions\CreateCompetitionAction;
use App\Enums\CompetitionType;
use App\Enums\StageStatus;
use App\Enums\StageType;
use App\Models\Competition;
use App\Models\CompetitionStage;
use App\Models\Team;
use Illuminate\Support\Facades\Http;
use Laravel\Prompts\Prompt;

beforeEach(function () {
    // Generating a bracket fires BracketGenerated, which the webhook listener
    // turns into outbound HTTP calls. Keep the test hermetic.
    Http::fake();

    // resolveStage() may use Laravel\Prompts\select; route to the
    // non-interactive fallback so the Artisan harness can drive it.
    Prompt::fallbackWhen(true);
});

afterEach(function () {
    Prompt::fallbackWhen(false);
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

it('generates a bracket for an explicitly selected --stage', function () {
    $competition = makeBracketCompetition(4);
    $stage = $competition->stages()->first();

    $this->artisan('competition:generate-bracket', [
        'competition' => $competition->id,
        '--stage' => $stage->id,
    ])
        ->expectsOutputToContain('Bracket generated:')
        ->assertExitCode(0);

    expect($competition->matches()->count())->toBeGreaterThan(0);
});

it('fails when the given --stage does not belong to the competition', function () {
    $competition = makeBracketCompetition(4);

    $this->artisan('competition:generate-bracket', [
        'competition' => $competition->id,
        '--stage' => '01999999999999999999999999',
    ])
        ->expectsOutputToContain('Stage not found in this competition.')
        ->assertExitCode(1);
});

it('prompts to choose a stage when several exist', function () {
    $competition = makeBracketCompetition(4);
    $firstStage = $competition->stages()->first();

    // A second stage forces resolveStage() into its interactive select branch.
    CompetitionStage::factory()->create([
        'competition_id' => $competition->id,
        'stage_type' => StageType::SingleElimination,
        'order' => 2,
    ]);

    $this->artisan('competition:generate-bracket', [
        'competition' => $competition->id,
    ])
        ->expectsQuestion('Select a stage', $firstStage->id)
        ->expectsOutputToContain('Bracket generated:')
        ->assertExitCode(0);
});

it('fails when the competition has no stages', function () {
    $competition = Competition::factory()->create();

    $this->artisan('competition:generate-bracket', [
        'competition' => $competition->id,
    ])
        ->expectsOutputToContain('No stages defined for this competition.')
        ->assertExitCode(1);
});
