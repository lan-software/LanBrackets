<?php

namespace App\Console\Commands;

use App\Actions\AddCompetitionParticipantAction;
use App\Actions\AddCompetitionStageAction;
use App\Actions\CreateCompetitionAction;
use App\Actions\GenerateBracketAction;
use App\Actions\ReportMatchResultAction;
use App\Enums\CompetitionStatus;
use App\Enums\CompetitionType;
use App\Enums\MatchStatus;
use App\Enums\StageType;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\CompetitionStage;
use App\Models\Team;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

#[Signature('db:seed-demo {--fresh : Truncate existing demo data before seeding}')]
#[Description('Seed the database with demo tournaments in various states for all formats')]
class SeedDemo extends Command
{
    /** @var string[] */
    protected array $teamNames = [
        'Velocity Storm',
        'Digital Wolves',
        'Neon Crusaders',
        'Shadow Collective',
        'Arctic Foxes',
        'Iron Vanguard',
        'Phoenix Rising',
        'Lunar Eclipse',
        'Thunder Hawks',
        'Crimson Tide',
        'Omega Squad',
        'Frost Giants',
        'Solar Flare',
        'Apex Predators',
        'Ghost Protocol',
        'Quantum Drift',
        'Vortex Gaming',
        'Nova Corps',
        'Dark Matter',
        'Cyber Knights',
        'Prism Effect',
        'Zero Gravity',
        'Blaze Runners',
        'Titan Force',
        'Storm Breakers',
        'Rogue Element',
        'Night Owls',
        'Crystal Edge',
        'Rapid Fire',
        'Steel Horizon',
        'Echo Chamber',
        'Binary Stars',
    ];

    public function handle(
        CreateCompetitionAction $createCompetition,
        AddCompetitionParticipantAction $addParticipant,
        GenerateBracketAction $generateBracket,
        ReportMatchResultAction $reportResult,
    ): int {
        if ($this->option('fresh')) {
            $this->info('Clearing existing demo data...');
            Competition::where('name', 'like', '[Demo]%')->each(fn (Competition $c) => $c->delete());
            Team::where('name', 'like', 'Demo:%')->each(fn (Team $t) => $t->delete());
        }

        $this->info('Creating demo teams...');
        $teams = $this->createTeams();
        $this->line("  Created {$teams->count()} teams.");

        // ── Single Elimination ──────────────────────────────────────────

        $this->newLine();
        $this->info('Seeding Single Elimination tournaments...');

        $this->seedTournament(
            $createCompetition, $addParticipant, $generateBracket, $reportResult,
            name: '[Demo] SE 16-Team — Finished',
            stageType: StageType::SingleElimination,
            teams: $teams->take(16),
            options: ['settings' => ['third_place_match' => true]],
            playState: 'finished',
        );

        $this->seedTournament(
            $createCompetition, $addParticipant, $generateBracket, $reportResult,
            name: '[Demo] SE 8-Team — In Progress',
            stageType: StageType::SingleElimination,
            teams: $teams->slice(0, 8),
            options: [],
            playState: 'partial',
        );

        $this->seedTournament(
            $createCompetition, $addParticipant, $generateBracket, $reportResult,
            name: '[Demo] SE 5-Team (BYEs) — Ready',
            stageType: StageType::SingleElimination,
            teams: $teams->slice(0, 5),
            options: ['settings' => ['third_place_match' => true]],
            playState: 'ready',
        );

        $this->seedTournament(
            $createCompetition, $addParticipant, $generateBracket, $reportResult,
            name: '[Demo] SE 29-Team (BYEs) — Finished',
            stageType: StageType::SingleElimination,
            teams: $teams->take(29),
            options: ['settings' => ['third_place_match' => true]],
            playState: 'finished',
        );

        // ── Double Elimination ──────────────────────────────────────────

        $this->newLine();
        $this->info('Seeding Double Elimination tournaments...');

        $this->seedTournament(
            $createCompetition, $addParticipant, $generateBracket, $reportResult,
            name: '[Demo] DE 8-Team + Reset — Finished',
            stageType: StageType::DoubleElimination,
            teams: $teams->slice(0, 8),
            options: ['settings' => ['grand_final_reset' => true, 'third_place_match' => true]],
            playState: 'finished',
        );

        $this->seedTournament(
            $createCompetition, $addParticipant, $generateBracket, $reportResult,
            name: '[Demo] DE 16-Team — In Progress',
            stageType: StageType::DoubleElimination,
            teams: $teams->take(16),
            options: ['settings' => ['grand_final_reset' => true]],
            playState: 'partial',
        );

        $this->seedTournament(
            $createCompetition, $addParticipant, $generateBracket, $reportResult,
            name: '[Demo] DE 6-Team — Ready',
            stageType: StageType::DoubleElimination,
            teams: $teams->slice(0, 6),
            options: ['settings' => ['third_place_match' => true]],
            playState: 'ready',
        );

        $this->seedTournament(
            $createCompetition, $addParticipant, $generateBracket, $reportResult,
            name: '[Demo] DE 29-Team + Reset — Finished',
            stageType: StageType::DoubleElimination,
            teams: $teams->take(29),
            options: ['settings' => ['grand_final_reset' => true, 'third_place_match' => true]],
            playState: 'finished',
        );

        // ── Round Robin ─────────────────────────────────────────────────

        $this->newLine();
        $this->info('Seeding Round Robin tournaments...');

        $this->seedTournament(
            $createCompetition, $addParticipant, $generateBracket, $reportResult,
            name: '[Demo] RR 6-Team — Finished',
            stageType: StageType::RoundRobin,
            teams: $teams->slice(0, 6),
            options: ['settings' => ['points_win' => 3, 'points_draw' => 1, 'allow_draws' => true]],
            playState: 'finished',
        );

        $this->seedTournament(
            $createCompetition, $addParticipant, $generateBracket, $reportResult,
            name: '[Demo] RR 8-Team — In Progress',
            stageType: StageType::RoundRobin,
            teams: $teams->slice(0, 8),
            options: ['settings' => ['allow_draws' => true]],
            playState: 'partial',
        );

        $this->seedTournament(
            $createCompetition, $addParticipant, $generateBracket, $reportResult,
            name: '[Demo] RR 5-Team (BYEs) — Ready',
            stageType: StageType::RoundRobin,
            teams: $teams->slice(0, 5),
            options: [],
            playState: 'ready',
        );

        $this->seedTournament(
            $createCompetition, $addParticipant, $generateBracket, $reportResult,
            name: '[Demo] RR 29-Team (BYEs) — Finished',
            stageType: StageType::RoundRobin,
            teams: $teams->take(29),
            options: ['settings' => ['points_win' => 3, 'points_draw' => 1, 'allow_draws' => true]],
            playState: 'finished',
        );

        // ── Swiss ───────────────────────────────────────────────────────

        $this->newLine();
        $this->info('Seeding Swiss tournaments...');

        $this->seedTournament(
            $createCompetition, $addParticipant, $generateBracket, $reportResult,
            name: '[Demo] Swiss 8-Team — Finished',
            stageType: StageType::Swiss,
            teams: $teams->slice(0, 8),
            options: [],
            playState: 'finished',
        );

        $this->seedTournament(
            $createCompetition, $addParticipant, $generateBracket, $reportResult,
            name: '[Demo] Swiss 12-Team — In Progress',
            stageType: StageType::Swiss,
            teams: $teams->slice(0, 12),
            options: ['settings' => ['total_rounds' => 4]],
            playState: 'partial',
        );

        $this->seedTournament(
            $createCompetition, $addParticipant, $generateBracket, $reportResult,
            name: '[Demo] Swiss 16-Team — Ready',
            stageType: StageType::Swiss,
            teams: $teams->take(16),
            options: [],
            playState: 'ready',
        );

        $this->seedTournament(
            $createCompetition, $addParticipant, $generateBracket, $reportResult,
            name: '[Demo] Swiss 29-Team — Finished',
            stageType: StageType::Swiss,
            teams: $teams->take(29),
            options: ['settings' => ['total_rounds' => 5]],
            playState: 'finished',
        );

        // ── Group Stage ─────────────────────────────────────────────────

        $this->newLine();
        $this->info('Seeding Group Stage tournaments...');

        $this->seedTournament(
            $createCompetition, $addParticipant, $generateBracket, $reportResult,
            name: '[Demo] GS 8-Team (2 Groups) — Finished',
            stageType: StageType::GroupStage,
            teams: $teams->slice(0, 8),
            options: ['settings' => ['group_count' => 2, 'allow_draws' => true]],
            playState: 'finished',
        );

        $this->seedTournament(
            $createCompetition, $addParticipant, $generateBracket, $reportResult,
            name: '[Demo] GS 12-Team (3 Groups) — In Progress',
            stageType: StageType::GroupStage,
            teams: $teams->slice(0, 12),
            options: ['settings' => ['group_count' => 3, 'allow_draws' => true]],
            playState: 'partial',
        );

        $this->seedTournament(
            $createCompetition, $addParticipant, $generateBracket, $reportResult,
            name: '[Demo] GS 16-Team (4 Groups) — Ready',
            stageType: StageType::GroupStage,
            teams: $teams->take(16),
            options: ['settings' => ['group_count' => 4]],
            playState: 'ready',
        );

        $this->seedTournament(
            $createCompetition, $addParticipant, $generateBracket, $reportResult,
            name: '[Demo] GS 29-Team (4 Groups) — Finished',
            stageType: StageType::GroupStage,
            teams: $teams->take(29),
            options: ['settings' => ['group_count' => 4, 'allow_draws' => true]],
            playState: 'finished',
        );

        // ── Multi-Stage ─────────────────────────────────────────────────

        $this->newLine();
        $this->info('Seeding Multi-Stage tournaments...');

        $this->seedMultiStageTournament(
            $createCompetition, $addParticipant, $generateBracket, $reportResult,
            app(AddCompetitionStageAction::class),
            teams: $teams->take(16),
        );

        $this->newLine();
        $this->info('Demo seeding complete!');

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Team>
     */
    protected function createTeams(): Collection
    {
        $teams = collect();

        foreach ($this->teamNames as $name) {
            $prefixed = "Demo: {$name}";
            $team = Team::firstOrCreate(
                ['slug' => Str::slug($prefixed)],
                [
                    'name' => $prefixed,
                    'slug' => Str::slug($prefixed),
                    'tag' => strtoupper(Str::substr($name, 0, 3)),
                    'description' => "Demo team — {$name}",
                    'status' => 'active',
                ],
            );
            $teams->push($team);
        }

        return $teams;
    }

    /**
     * Seed a single tournament with the given play state.
     *
     * @param  Collection<int, Team>  $teams
     * @param  array<string, mixed>  $options
     */
    protected function seedTournament(
        CreateCompetitionAction $createCompetition,
        AddCompetitionParticipantAction $addParticipant,
        GenerateBracketAction $generateBracket,
        ReportMatchResultAction $reportResult,
        string $name,
        StageType $stageType,
        Collection $teams,
        array $options,
        string $playState,
    ): void {
        if (Competition::where('name', $name)->exists()) {
            $this->line("  {$name} — already exists, skipping.");

            return;
        }

        $competition = $createCompetition->execute(
            name: $name,
            type: CompetitionType::Tournament,
            stageType: $stageType,
            options: $options,
        );

        foreach ($teams->values() as $index => $team) {
            $addParticipant->execute($competition, $team, $index + 1);
        }

        $stage = $competition->stages()->first();
        $generateBracket->execute($stage);
        $competition = $competition->fresh(['stages']);

        $matchTotal = CompetitionMatch::where('competition_stage_id', $stage->id)->count();
        $stateLabel = match ($playState) {
            'finished' => 'finished',
            'partial' => 'in progress',
            'ready' => 'ready to start',
        };

        match ($playState) {
            'finished' => $this->playAllMatches($reportResult, $competition),
            'partial' => $this->playPartialMatches($reportResult, $competition, $stageType),
            'ready' => null,
        };

        $played = CompetitionMatch::where('competition_stage_id', $stage->id)
            ->where('status', MatchStatus::Finished)
            ->count();

        $this->line("  {$name} (ID: {$competition->id}) — {$played}/{$matchTotal} matches ({$stateLabel})");
    }

    /**
     * Play through ALL matches until the tournament is finished.
     */
    protected function playAllMatches(ReportMatchResultAction $reportResult, Competition $competition): void
    {
        $stage = $competition->stages()->first();
        $safety = 0;
        $maxIterations = 500;

        while ($safety++ < $maxIterations) {
            $match = $this->findNextPlayableMatch($stage->id);

            if ($match === null) {
                break;
            }

            $this->resolveMatch($reportResult, $match);
        }

        $competition->update(['status' => CompetitionStatus::Finished]);
    }

    /**
     * Play a portion of the tournament to create an "in progress" state.
     *
     * Format-aware: plays different amounts depending on the format type.
     */
    protected function playPartialMatches(
        ReportMatchResultAction $reportResult,
        Competition $competition,
        StageType $stageType,
    ): void {
        $stage = $competition->stages()->first();

        match ($stageType) {
            StageType::SingleElimination,
            StageType::DoubleElimination => $this->playEliminationPartial($reportResult, $stage),
            StageType::RoundRobin,
            StageType::GroupStage => $this->playRoundBasedPartial($reportResult, $stage, rounds: 2),
            StageType::Swiss => $this->playSwissPartial($reportResult, $stage, rounds: 2),
            default => null,
        };
    }

    /**
     * Play round 1 + some round 2 for elimination formats (including LB).
     */
    protected function playEliminationPartial(ReportMatchResultAction $reportResult, CompetitionStage $stage): void
    {
        // Play all of round 1
        $this->playRound($reportResult, $stage->id, 1);

        // Play round 2 matches that became ready
        $this->playReadyMatchesInRound($reportResult, $stage->id, 2);

        // Play LB round 101 if any are ready (double elimination)
        $this->playReadyMatchesInRound($reportResult, $stage->id, 101);
    }

    /**
     * Play N complete rounds for round-based formats (Round Robin, Group Stage).
     */
    protected function playRoundBasedPartial(
        ReportMatchResultAction $reportResult,
        CompetitionStage $stage,
        int $rounds,
    ): void {
        for ($round = 1; $round <= $rounds; $round++) {
            $this->playRound($reportResult, $stage->id, $round);
        }
    }

    /**
     * Play N complete rounds for Swiss (which generates rounds dynamically).
     */
    protected function playSwissPartial(
        ReportMatchResultAction $reportResult,
        CompetitionStage $stage,
        int $rounds,
    ): void {
        for ($round = 1; $round <= $rounds; $round++) {
            $pending = CompetitionMatch::query()
                ->where('competition_stage_id', $stage->id)
                ->where('round_number', $round)
                ->where('status', MatchStatus::Pending)
                ->get();

            foreach ($pending as $match) {
                if ($match->matchParticipants()->count() === 2) {
                    $this->resolveMatch($reportResult, $match);
                }
            }
        }
    }

    /**
     * Play all pending matches in a specific round that have 2 participants.
     */
    protected function playRound(ReportMatchResultAction $reportResult, int $stageId, int $roundNumber): void
    {
        $matches = CompetitionMatch::query()
            ->where('competition_stage_id', $stageId)
            ->where('round_number', $roundNumber)
            ->where('status', MatchStatus::Pending)
            ->get();

        foreach ($matches as $match) {
            if ($match->matchParticipants()->count() === 2) {
                $this->resolveMatch($reportResult, $match);
            }
        }
    }

    /**
     * Play matches in a specific round that became ready (have 2 participants).
     */
    protected function playReadyMatchesInRound(ReportMatchResultAction $reportResult, int $stageId, int $roundNumber): void
    {
        $safety = 0;

        while ($safety++ < 50) {
            $match = CompetitionMatch::query()
                ->where('competition_stage_id', $stageId)
                ->where('round_number', $roundNumber)
                ->where('status', MatchStatus::Pending)
                ->withCount('matchParticipants')
                ->get()
                ->where('match_participants_count', 2)
                ->first();

            if ($match === null) {
                break;
            }

            $this->resolveMatch($reportResult, $match);
        }
    }

    /**
     * Find the next match that has 2 participants and is still pending.
     */
    protected function findNextPlayableMatch(int $stageId): ?CompetitionMatch
    {
        return CompetitionMatch::query()
            ->where('competition_stage_id', $stageId)
            ->where('status', MatchStatus::Pending)
            ->orderBy('round_number')
            ->orderBy('sequence')
            ->withCount('matchParticipants')
            ->get()
            ->where('match_participants_count', 2)
            ->first();
    }

    /**
     * Resolve a match with randomised but plausible scores.
     *
     * Produces varied results: decisive wins, close games, and occasional draws
     * for formats that allow them.
     */
    protected function resolveMatch(ReportMatchResultAction $reportResult, CompetitionMatch $match): void
    {
        $stage = $match->stage;
        $bestOf = $stage->settings['best_of'] ?? 1;
        $allowDraws = $stage->settings['allow_draws'] ?? false;

        if ($bestOf > 1) {
            $winsNeeded = (int) ceil($bestOf / 2);
            $winnerScore = $winsNeeded;
            $loserScore = rand(0, $winsNeeded - 1);
        } else {
            // For best_of=1, generate more varied and realistic scores
            $winnerScore = rand(1, 5);
            $loserScore = rand(0, max(0, $winnerScore - 1));
        }

        // ~20% chance of a draw in formats that allow it
        if ($allowDraws && rand(1, 5) === 1) {
            $drawScore = rand(0, 3);
            $scores = [1 => $drawScore, 2 => $drawScore];
        } else {
            // Randomly assign which slot wins
            if (rand(0, 1) === 0) {
                $scores = [1 => $winnerScore, 2 => $loserScore];
            } else {
                $scores = [1 => $loserScore, 2 => $winnerScore];
            }

            // Safety: ensure no accidental ties for non-draw formats
            if (! $allowDraws && $scores[1] === $scores[2]) {
                $scores[1]++;
            }
        }

        $reportResult->execute($match, $scores);
    }

    /**
     * Seed a multi-stage tournament: Group Stage (4 groups) → SE Playoffs.
     *
     * @param  Collection<int, Team>  $teams
     */
    protected function seedMultiStageTournament(
        CreateCompetitionAction $createCompetition,
        AddCompetitionParticipantAction $addParticipant,
        GenerateBracketAction $generateBracket,
        ReportMatchResultAction $reportResult,
        AddCompetitionStageAction $addStage,
        Collection $teams,
    ): void {
        $name = '[Demo] GS → SE 16-Team — Finished';

        if (Competition::where('name', $name)->exists()) {
            $this->line("  {$name} — already exists, skipping.");

            return;
        }

        $competition = $createCompetition->execute(
            name: $name,
            type: CompetitionType::Tournament,
            stageType: StageType::GroupStage,
            options: ['settings' => ['group_count' => 4, 'allow_draws' => true]],
        );

        // Add playoff stage — sets progression_meta on the group stage
        $addStage->execute($competition, StageType::SingleElimination, [
            'name' => 'Playoffs',
            'settings' => ['third_place_match' => true],
            'progression_meta' => ['per_group' => 2],
        ]);

        foreach ($teams->values() as $index => $team) {
            $addParticipant->execute($competition, $team, $index + 1);
        }

        // Generate and play through group stage
        $groupStage = $competition->stages()->where('order', 1)->first();
        $generateBracket->execute($groupStage);

        $this->playAllMatches($reportResult, $competition);

        // After group stage completes, advancement and playoff generation happens automatically.
        // Now play through the playoffs.
        $competition->refresh();
        $playoffs = $competition->stages()->where('order', 2)->first();

        if ($playoffs !== null) {
            $this->playAllStageMatches($reportResult, $playoffs);
        }

        $competition->refresh();

        $gsMatches = CompetitionMatch::where('competition_stage_id', $groupStage->id)
            ->where('status', MatchStatus::Finished)->count();
        $gsTotal = CompetitionMatch::where('competition_stage_id', $groupStage->id)->count();

        $playoffMatches = $playoffs ? CompetitionMatch::where('competition_stage_id', $playoffs->id)
            ->where('status', MatchStatus::Finished)->count() : 0;
        $playoffTotal = $playoffs ? CompetitionMatch::where('competition_stage_id', $playoffs->id)->count() : 0;

        $this->line("  {$name} (ID: {$competition->id}) — Groups: {$gsMatches}/{$gsTotal}, Playoffs: {$playoffMatches}/{$playoffTotal} ({$competition->status->value})");
    }

    /**
     * Play all matches in a specific stage.
     */
    protected function playAllStageMatches(ReportMatchResultAction $reportResult, CompetitionStage $stage): void
    {
        $safety = 0;
        $maxIterations = 500;

        while ($safety++ < $maxIterations) {
            $match = $this->findNextPlayableMatch($stage->id);

            if ($match === null) {
                break;
            }

            $this->resolveMatch($reportResult, $match);
        }
    }
}
