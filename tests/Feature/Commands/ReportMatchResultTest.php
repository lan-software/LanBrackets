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

beforeEach(function () {
    // Reporting a result fires MatchResultReported, which the webhook listener
    // turns into outbound HTTP calls. Keep the test hermetic.
    Http::fake();
});

/**
 * Build a competition with a single playable match between two teams.
 *
 * @return array{competition: Competition, match: CompetitionMatch}
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

    return ['competition' => $competition, 'match' => $match];
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

/*
 * NOTE: resolveMatch() casts the --match option to (int) before calling find()
 * (ReportMatchResult.php:80). Because CompetitionMatch uses ULID string keys,
 * any real ULID is cast to an integer that never matches a row, so the
 * --match success path is unreachable through this command as written. The
 * test below documents that behaviour: a valid, in-competition match passed via
 * --match still reports "Match not found" and exits 1. Fixing the cast is an
 * application change, which is out of scope for these tests.
 */
it('cannot resolve a ULID match via --match because of the (int) cast (known app bug)', function () {
    ['competition' => $competition, 'match' => $match] = makeReportableMatch();

    $this->artisan('competition:report-result', [
        'competition' => $competition->id,
        '--match' => $match->id,
        '--score1' => '3',
        '--score2' => '1',
    ])
        ->expectsOutputToContain('Match not found in this competition.')
        ->assertExitCode(1);

    // The match was never resolved, so it stays pending.
    expect($match->fresh()->status)->toBe(MatchStatus::Pending);
});

it('warns when no matches are ready to be reported interactively', function () {
    $competition = Competition::factory()->create();

    // Without --match the command lists ready matches; there are none here.
    $this->artisan('competition:report-result', [
        'competition' => $competition->id,
    ])
        ->expectsOutputToContain('No matches are ready to be played.')
        ->assertExitCode(1);
});
