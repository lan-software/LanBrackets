<?php

use App\Actions\AddCompetitionParticipantAction;
use App\Actions\CreateCompetitionAction;
use App\Enums\CompetitionType;
use App\Enums\MatchStatus;
use App\Enums\StageType;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\MatchParticipant;
use App\Models\Team;
use Illuminate\Support\Facades\Http;
use Laravel\Prompts\Prompt;

beforeEach(function () {
    // Reporting a result fires MatchResultReported, which the webhook listener
    // turns into outbound HTTP calls. Keep the test hermetic.
    Http::fake();

    // resolveMatch()/resolveScores() use Laravel\Prompts; route them to the
    // non-interactive fallback so the Artisan harness can drive them.
    Prompt::fallbackWhen(true);
});

afterEach(function () {
    Prompt::fallbackWhen(false);
});

/**
 * Build a competition with a single playable match between two teams.
 *
 * @return array{competition: Competition, match: CompetitionMatch, cp1: mixed, cp2: mixed}
 */
function makeReportableMatch(): array
{
    $competition = app(CreateCompetitionAction::class)->execute(
        name: 'Report Cup '.uniqid(),
        type: CompetitionType::Tournament,
        stageType: StageType::SingleElimination,
    );

    $addParticipant = app(AddCompetitionParticipantAction::class);
    $cp1 = $addParticipant->execute($competition, Team::factory()->create(['name' => 'Alpha']), 1);
    $cp2 = $addParticipant->execute($competition, Team::factory()->create(['name' => 'Bravo']), 2);

    $stage = $competition->stages()->first();

    $match = CompetitionMatch::factory()->create([
        'competition_id' => $competition->id,
        'competition_stage_id' => $stage->id,
        'status' => MatchStatus::Pending,
        'participant_1_id' => $cp1->id,
        'participant_2_id' => $cp2->id,
    ]);

    MatchParticipant::factory()->create([
        'match_id' => $match->id,
        'competition_participant_id' => $cp1->id,
        'slot' => 1,
    ]);
    MatchParticipant::factory()->create([
        'match_id' => $match->id,
        'competition_participant_id' => $cp2->id,
        'slot' => 2,
    ]);

    return ['competition' => $competition, 'match' => $match, 'cp1' => $cp1, 'cp2' => $cp2];
}

it('fails when the competition does not exist', function () {
    $this->artisan('competition:report-result', [
        'competition' => '01999999999999999999999999',
        '--match' => '01999999999999999999999999',
    ])
        ->expectsOutputToContain('Competition not found.')
        ->assertExitCode(1);
});

it('fails when the match is not part of the competition', function () {
    $competition = Competition::factory()->create();

    $this->artisan('competition:report-result', [
        'competition' => $competition->id,
        '--match' => '01999999999999999999999999',
    ])
        ->expectsOutputToContain('Match not found in this competition.')
        ->assertExitCode(1);
});

it('reports a result via --match and completes the match', function () {
    ['competition' => $competition, 'match' => $match, 'cp1' => $cp1] = makeReportableMatch();

    $this->artisan('competition:report-result', [
        'competition' => $competition->id,
        '--match' => $match->id,
        '--score1' => '3',
        '--score2' => '1',
    ])
        ->expectsOutputToContain('Match result reported successfully.')
        ->assertExitCode(0);

    $match->refresh();
    expect($match->status)->toBe(MatchStatus::Completed);
    expect($match->score_participant_1)->toBe(3);
    expect($match->score_participant_2)->toBe(1);
    expect($match->winner_participant_id)->toBe($cp1->id);
});

it('reports a result interactively, prompting for the match and scores', function () {
    ['competition' => $competition, 'match' => $match, 'cp2' => $cp2] = makeReportableMatch();

    $this->artisan('competition:report-result', [
        'competition' => $competition->id,
    ])
        // The select() fallback matches on the option label, not the key.
        ->expectsQuestion('Select a match', "Match #{$match->id}")
        ->expectsQuestion('Score for participant 1', '1')
        ->expectsQuestion('Score for participant 2', '2')
        ->expectsOutputToContain('Match result reported successfully.')
        ->assertExitCode(0);

    $match->refresh();
    expect($match->status)->toBe(MatchStatus::Completed);
    expect($match->winner_participant_id)->toBe($cp2->id);
});

it('fails when the reported scores are a tie', function () {
    ['competition' => $competition, 'match' => $match] = makeReportableMatch();

    $this->artisan('competition:report-result', [
        'competition' => $competition->id,
        '--match' => $match->id,
        '--score1' => '2',
        '--score2' => '2',
    ])
        ->expectsOutputToContain('Match cannot end in a tie.')
        ->assertExitCode(1);

    expect($match->fresh()->status)->toBe(MatchStatus::Pending);
});

it('warns when no matches are ready to be reported interactively', function () {
    $competition = Competition::factory()->create();

    $this->artisan('competition:report-result', [
        'competition' => $competition->id,
    ])
        ->expectsOutputToContain('No matches are ready to be played.')
        ->assertExitCode(1);
});
