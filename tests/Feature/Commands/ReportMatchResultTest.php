<?php

use App\Actions\AddCompetitionParticipantAction;
use App\Actions\CreateCompetitionAction;
use App\Actions\GenerateBracketAction;
use App\Enums\CompetitionType;
use App\Enums\MatchStatus;
use App\Enums\StageType;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\Team;
use Illuminate\Support\Facades\Http;
use Laravel\Prompts\Prompt;

beforeEach(function () {
    // Reporting a result fires MatchResultReported, whose listener performs
    // outbound HTTP. Keep the test hermetic.
    Http::fake();

    // resolveMatch()/score prompts use Laravel\Prompts; route them to the
    // non-interactive fallback so the Artisan harness can drive them.
    Prompt::fallbackWhen(true);
});

afterEach(function () {
    Prompt::fallbackWhen(false);
});

/**
 * Build a single-elimination competition with two participants and a generated
 * bracket, returning the lone playable match plus the participant names.
 *
 * @return array{competition: Competition, match: CompetitionMatch, name1: string, name2: string}
 */
function makePlayableMatch(): array
{
    $competition = app(CreateCompetitionAction::class)->execute(
        name: 'Report Cup '.uniqid(),
        type: CompetitionType::Tournament,
        stageType: StageType::SingleElimination,
    );

    $add = app(AddCompetitionParticipantAction::class);
    $add->execute($competition, Team::factory()->create(['name' => 'Alpha']), 1);
    $add->execute($competition, Team::factory()->create(['name' => 'Bravo']), 2);

    $stage = $competition->stages()->first();
    app(GenerateBracketAction::class)->execute($stage);

    $match = $stage->matches()
        ->where('status', MatchStatus::Pending)
        ->with('matchParticipants.competitionParticipant.participant')
        ->first();

    $p1 = $match->matchParticipants->firstWhere('slot', 1);
    $p2 = $match->matchParticipants->firstWhere('slot', 2);

    return [
        'competition' => $competition,
        'match' => $match,
        'name1' => $p1?->competitionParticipant?->participant?->name ?? 'Slot 1',
        'name2' => $p2?->competitionParticipant?->participant?->name ?? 'Slot 2',
    ];
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

it('reports a result via --match and finishes the match', function () {
    ['competition' => $competition, 'match' => $match] = makePlayableMatch();

    $this->artisan('competition:report-result', [
        'competition' => $competition->id,
        '--match' => $match->id,
        '--score1' => '3',
        '--score2' => '1',
    ])
        ->expectsOutputToContain('Result recorded. Winner:')
        ->assertExitCode(0);

    expect($match->fresh()->status)->toBe(MatchStatus::Finished);
});

it('reports a result interactively, prompting for the match and scores', function () {
    ['competition' => $competition, 'match' => $match, 'name1' => $name1, 'name2' => $name2] = makePlayableMatch();

    $this->artisan('competition:report-result', [
        'competition' => $competition->id,
    ])
        ->expectsQuestion('Select a match to report', $match->id)
        ->expectsQuestion("Score for {$name1} (slot 1)", '5')
        ->expectsQuestion("Score for {$name2} (slot 2)", '2')
        ->expectsOutputToContain('Result recorded. Winner:')
        ->assertExitCode(0);

    expect($match->fresh()->status)->toBe(MatchStatus::Finished);
});

it('fails when a single-elimination match is reported as a tie', function () {
    ['competition' => $competition, 'match' => $match] = makePlayableMatch();

    $this->artisan('competition:report-result', [
        'competition' => $competition->id,
        '--match' => $match->id,
        '--score1' => '2',
        '--score2' => '2',
    ])
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
