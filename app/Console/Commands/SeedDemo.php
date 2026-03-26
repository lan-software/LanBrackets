<?php

namespace App\Console\Commands;

use App\Actions\AddCompetitionParticipantAction;
use App\Actions\CreateCompetitionAction;
use App\Actions\GenerateBracketAction;
use App\Actions\ReportMatchResultAction;
use App\Enums\CompetitionStatus;
use App\Enums\CompetitionType;
use App\Enums\MatchStatus;
use App\Enums\StageType;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\Team;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

#[Signature('db:seed-demo {--fresh : Truncate existing demo data before seeding}')]
#[Description('Seed the database with demo tournaments in various states')]
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

        $this->newLine();
        $this->info('Seeding Single Elimination tournaments...');
        $this->seedFormat(
            createCompetition: $createCompetition,
            addParticipant: $addParticipant,
            generateBracket: $generateBracket,
            reportResult: $reportResult,
            stageType: StageType::SingleElimination,
            teams: $teams,
            participantCount: 16,
            options: [
                'finished' => ['settings' => ['third_place_match' => true]],
                'partial' => [],
                'ready' => ['settings' => ['third_place_match' => true]],
            ],
        );

        $this->newLine();
        $this->info('Seeding Double Elimination tournaments...');
        $this->seedFormat(
            createCompetition: $createCompetition,
            addParticipant: $addParticipant,
            generateBracket: $generateBracket,
            reportResult: $reportResult,
            stageType: StageType::DoubleElimination,
            teams: $teams,
            participantCount: 16,
            options: [
                'finished' => ['settings' => ['grand_final_reset' => true, 'third_place_match' => true]],
                'partial' => ['settings' => ['grand_final_reset' => true]],
                'ready' => ['settings' => ['third_place_match' => true]],
            ],
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
     * Seed a specific format with finished, partial, and ready-to-start tournaments.
     *
     * @param  Collection<int, Team>  $teams
     * @param  array{finished?: array<string, mixed>, partial?: array<string, mixed>, ready?: array<string, mixed>}  $options
     */
    protected function seedFormat(
        CreateCompetitionAction $createCompetition,
        AddCompetitionParticipantAction $addParticipant,
        GenerateBracketAction $generateBracket,
        ReportMatchResultAction $reportResult,
        StageType $stageType,
        Collection $teams,
        int $participantCount,
        array $options,
    ): void {
        $label = str_replace('_', ' ', ucfirst($stageType->value));

        // (a) Finished tournament
        $this->line("  Creating finished {$label}...");
        $finished = $this->createTournament(
            createCompetition: $createCompetition,
            addParticipant: $addParticipant,
            generateBracket: $generateBracket,
            name: "[Demo] {$label} — Finished",
            stageType: $stageType,
            teams: $teams->take($participantCount),
            stageSettings: $options['finished'] ?? [],
        );
        $this->playAllMatches($reportResult, $finished);
        $this->line("    ✓ {$finished->name} (ID: {$finished->id})");

        // (b) Partially finished tournament
        $this->line("  Creating partially played {$label}...");
        $partial = $this->createTournament(
            createCompetition: $createCompetition,
            addParticipant: $addParticipant,
            generateBracket: $generateBracket,
            name: "[Demo] {$label} — In Progress",
            stageType: $stageType,
            teams: $teams->slice(4)->take($participantCount),
            stageSettings: $options['partial'] ?? [],
        );
        $this->playPartialMatches($reportResult, $partial);
        $this->line("    ✓ {$partial->name} (ID: {$partial->id})");

        // (c) Ready-to-start tournament
        $this->line("  Creating ready-to-start {$label}...");
        $ready = $this->createTournament(
            createCompetition: $createCompetition,
            addParticipant: $addParticipant,
            generateBracket: $generateBracket,
            name: "[Demo] {$label} — Ready",
            stageType: $stageType,
            teams: $teams->slice(8)->take($participantCount),
            stageSettings: $options['ready'] ?? [],
        );
        $this->line("    ✓ {$ready->name} (ID: {$ready->id})");
    }

    /**
     * Create a tournament competition, add participants, and generate bracket.
     *
     * @param  Collection<int, Team>  $teams
     * @param  array<string, mixed>  $stageSettings
     */
    protected function createTournament(
        CreateCompetitionAction $createCompetition,
        AddCompetitionParticipantAction $addParticipant,
        GenerateBracketAction $generateBracket,
        string $name,
        StageType $stageType,
        Collection $teams,
        array $stageSettings,
    ): Competition {
        $competition = $createCompetition->execute(
            name: $name,
            type: CompetitionType::Tournament,
            stageType: $stageType,
            options: $stageSettings,
        );

        foreach ($teams->values() as $index => $team) {
            $addParticipant->execute($competition, $team, $index + 1);
        }

        $stage = $competition->stages()->first();
        $generateBracket->execute($stage);

        return $competition->fresh(['stages']);
    }

    /**
     * Play through ALL matches until the tournament is finished.
     */
    protected function playAllMatches(ReportMatchResultAction $reportResult, Competition $competition): void
    {
        $stage = $competition->stages()->first();
        $safety = 0;
        $maxIterations = 200;

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
     * Play through only the first round of WB matches (and any auto-resolved BYE matches).
     */
    protected function playPartialMatches(ReportMatchResultAction $reportResult, Competition $competition): void
    {
        $stage = $competition->stages()->first();

        // Play round 1 of winner's bracket first
        $round1Matches = CompetitionMatch::query()
            ->where('competition_stage_id', $stage->id)
            ->where('round_number', 1)
            ->where('status', MatchStatus::Pending)
            ->get();

        foreach ($round1Matches as $match) {
            if ($match->matchParticipants()->count() === 2) {
                $this->resolveMatch($reportResult, $match);
            }
        }

        // Now play any resultant LB/WB round 2 matches that became playable
        $safety = 0;

        while ($safety++ < 50) {
            $match = CompetitionMatch::query()
                ->where('competition_stage_id', $stage->id)
                ->where('round_number', '<=', 2)
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

        // Also play LB round 101 if any matches are ready
        $safety = 0;

        while ($safety++ < 50) {
            $match = CompetitionMatch::query()
                ->where('competition_stage_id', $stage->id)
                ->where('round_number', 101)
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
     */
    protected function resolveMatch(ReportMatchResultAction $reportResult, CompetitionMatch $match): void
    {
        $stage = $match->stage;
        $bestOf = $stage->settings['best_of'] ?? 1;
        $winsNeeded = (int) ceil($bestOf / 2);

        // Random winner: one side gets winsNeeded, loser gets less
        $winnerScore = $winsNeeded;
        $loserScore = rand(0, $winsNeeded - 1);

        // Randomly assign which slot wins
        if (rand(0, 1) === 0) {
            $scores = [1 => $winnerScore, 2 => $loserScore];
        } else {
            $scores = [1 => $loserScore, 2 => $winnerScore];
        }

        $reportResult->execute($match, $scores);
    }
}
