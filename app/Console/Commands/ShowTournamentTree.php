<?php

namespace App\Console\Commands;

use App\Enums\MatchStatus;
use App\Enums\StageType;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\CompetitionStage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tournament:tree {competition : The competition ID} {--stage= : Specific stage ID (optional)}')]
#[Description('Display a tournament bracket tree in ASCII')]
class ShowTournamentTree extends Command
{
    public function handle(): int
    {
        $competition = Competition::find($this->argument('competition'));

        if ($competition === null) {
            $this->error('Competition not found.');

            return self::FAILURE;
        }

        $this->info("Tournament: {$competition->name}");
        $this->newLine();

        $stages = $competition->stages()
            ->whereIn('stage_type', [StageType::SingleElimination, StageType::DoubleElimination])
            ->get();

        if ($this->option('stage')) {
            $stages = $stages->where('id', (int) $this->option('stage'));
        }

        if ($stages->isEmpty()) {
            $this->warn('No elimination stages found.');

            return self::SUCCESS;
        }

        foreach ($stages as $stage) {
            $this->renderStage($stage);
        }

        return self::SUCCESS;
    }

    protected function renderStage(CompetitionStage $stage): void
    {
        $this->info("Stage: {$stage->name} ({$stage->stage_type->value})");
        $this->line(str_repeat('─', 60));

        $matches = $stage->matches()
            ->with(['matchParticipants.competitionParticipant.participant'])
            ->orderBy('round_number')
            ->orderBy('sequence')
            ->get();

        if ($matches->isEmpty()) {
            $this->warn('  No matches generated yet.');

            return;
        }

        if ($stage->stage_type === StageType::DoubleElimination) {
            $this->renderDoubleElimination($matches);
        } else {
            $this->renderSingleElimination($matches);
        }

        $this->newLine();
    }

    protected function renderSingleElimination($matches): void
    {
        $thirdPlace = $matches->filter(fn ($m) => $m->settings['third_place'] ?? false);
        $regular = $matches->reject(fn ($m) => $m->settings['third_place'] ?? false);
        $rounds = $regular->groupBy('round_number')->sortKeys();

        foreach ($rounds as $roundNum => $roundMatches) {
            $this->renderRound("Round {$roundNum}", $roundMatches);
        }

        if ($thirdPlace->isNotEmpty()) {
            $this->newLine();
            $this->info('  ╔══ 3RD PLACE MATCH ══╗');
            $this->renderRound('3rd Place', $thirdPlace);
        }
    }

    protected function renderDoubleElimination($matches): void
    {
        $wb = $matches->filter(fn ($m) => ($m->settings['bracket_side'] ?? '') === 'winners');
        $lb = $matches->filter(fn ($m) => ($m->settings['bracket_side'] ?? '') === 'losers');
        $gf = $matches->filter(fn ($m) => ($m->settings['bracket_side'] ?? '') === 'grand_final');
        $tp = $matches->filter(fn ($m) => ($m->settings['bracket_side'] ?? '') === 'third_place');

        if ($wb->isNotEmpty()) {
            $this->info('  ╔══ WINNERS BRACKET ══╗');
            $rounds = $wb->groupBy('round_number')->sortKeys();
            foreach ($rounds as $roundNum => $roundMatches) {
                $this->renderRound("WB Round {$roundNum}", $roundMatches);
            }
        }

        if ($lb->isNotEmpty()) {
            $this->newLine();
            $this->info('  ╔══ LOSERS BRACKET ══╗');
            $rounds = $lb->groupBy('round_number')->sortKeys();
            foreach ($rounds as $roundNum => $roundMatches) {
                $label = 'LB Round '.($roundNum - 100);
                $this->renderRound($label, $roundMatches);
            }
        }

        if ($gf->isNotEmpty()) {
            $this->newLine();
            $this->info('  ╔══ GRAND FINAL ══╗');
            $this->renderRound('Grand Final', $gf);
        }

        if ($tp->isNotEmpty()) {
            $this->newLine();
            $this->info('  ╔══ 3RD PLACE MATCH ══╗');
            $this->renderRound('3rd Place', $tp);
        }
    }

    protected function renderRound(string $label, $matches): void
    {
        $this->line("  ┌─ {$label} ".str_repeat('─', max(1, 40 - strlen($label))));

        foreach ($matches as $match) {
            $this->renderMatch($match);
        }
    }

    protected function renderMatch(CompetitionMatch $match): void
    {
        $statusIcon = match ($match->status) {
            MatchStatus::Finished => '✓',
            MatchStatus::InProgress => '▶',
            MatchStatus::Pending => $this->isMatchReady($match) ? '★' : '○',
            default => '·',
        };

        $p1 = $match->matchParticipants->firstWhere('slot', 1);
        $p2 = $match->matchParticipants->firstWhere('slot', 2);

        $name1 = $p1 ? $this->participantName($p1) : '---';
        $name2 = $p2 ? $this->participantName($p2) : '---';
        $score1 = $p1?->score !== null ? (string) $p1->score : '-';
        $score2 = $p2?->score !== null ? (string) $p2->score : '-';

        $isReady = $match->status === MatchStatus::Pending && $this->isMatchReady($match);
        $highlight = $isReady ? "\e[33m" : '';
        $reset = $isReady ? "\e[0m" : '';

        $winnerMark = '';
        if ($match->status === MatchStatus::Finished && $match->winner_participant_id !== null) {
            $winnerMark = $p1?->competition_participant_id === $match->winner_participant_id
                ? ' ◀'
                : '';
        }

        $this->line("{$highlight}  │ [{$statusIcon}] M{$match->sequence}  {$this->pad($name1, 20)} {$score1}{$winnerMark}{$reset}");

        $loserMark = '';
        if ($match->status === MatchStatus::Finished && $match->winner_participant_id !== null) {
            $loserMark = $p2?->competition_participant_id === $match->winner_participant_id
                ? ' ◀'
                : '';
        }

        $this->line("{$highlight}  │       vs  {$this->pad($name2, 20)} {$score2}{$loserMark}{$reset}");
        $this->line('  │');
    }

    protected function participantName($matchParticipant): string
    {
        $cp = $matchParticipant->competitionParticipant;

        if ($cp === null) {
            return 'Unknown';
        }

        $entity = $cp->participant;

        if ($entity === null) {
            return "Seed #{$cp->seed}";
        }

        return $entity->name ?? "Seed #{$cp->seed}";
    }

    protected function isMatchReady(CompetitionMatch $match): bool
    {
        return $match->matchParticipants->count() === 2
            && $match->status === MatchStatus::Pending;
    }

    protected function pad(string $text, int $width): string
    {
        return str_pad(mb_substr($text, 0, $width), $width);
    }
}
