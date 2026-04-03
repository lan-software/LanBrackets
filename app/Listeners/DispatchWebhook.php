<?php

namespace App\Listeners;

use App\Events\BracketGenerated;
use App\Events\CompetitionCompleted;
use App\Events\MatchResultReported;
use App\Events\StageCompleted;
use App\Services\WebhookDispatcher;

class DispatchWebhook
{
    public function __construct(
        protected WebhookDispatcher $dispatcher,
    ) {}

    public function handleBracketGenerated(BracketGenerated $event): void
    {
        $stage = $event->stage->load('competition');

        $this->dispatcher->dispatch('bracket.generated', [
            'competition_id' => $stage->competition_id,
            'external_reference_id' => $stage->competition->external_reference_id,
            'stage_id' => $stage->id,
            'stage_name' => $stage->name,
            'match_count' => $stage->matches()->count(),
        ]);
    }

    public function handleMatchResultReported(MatchResultReported $event): void
    {
        $match = $event->match->load(['competition', 'matchParticipants']);

        $this->dispatcher->dispatch('match.result_reported', [
            'competition_id' => $match->competition_id,
            'external_reference_id' => $match->competition->external_reference_id,
            'stage_id' => $match->competition_stage_id,
            'match_id' => $match->id,
            'round_number' => $match->round_number,
            'sequence' => $match->sequence,
            'winner_participant_id' => $match->winner_participant_id,
            'loser_participant_id' => $match->loser_participant_id,
            'scores' => $match->matchParticipants->pluck('score', 'slot')->all(),
        ]);
    }

    public function handleStageCompleted(StageCompleted $event): void
    {
        $stage = $event->stage->load('competition');

        $this->dispatcher->dispatch('stage.completed', [
            'competition_id' => $stage->competition_id,
            'external_reference_id' => $stage->competition->external_reference_id,
            'stage_id' => $stage->id,
            'stage_name' => $stage->name,
        ]);
    }

    public function handleCompetitionCompleted(CompetitionCompleted $event): void
    {
        $competition = $event->competition;

        $this->dispatcher->dispatch('competition.completed', [
            'competition_id' => $competition->id,
            'external_reference_id' => $competition->external_reference_id,
        ]);
    }
}
