<?php

namespace App\Http\Controllers;

use App\Enums\MatchStatus;
use App\Models\Competition;
use Inertia\Inertia;
use Inertia\Response;

class CompetitionController extends Controller
{
    public function index(): Response
    {
        $competitions = Competition::query()
            ->withCount(['participants', 'stages'])
            ->orderByDesc('created_at')
            ->paginate(12);

        return Inertia::render('Competitions/Index', [
            'competitions' => $competitions,
        ]);
    }

    public function show(Competition $competition): Response
    {
        $competition->load([
            'stages.matches.matchParticipants.competitionParticipant.participant',
        ]);

        $stages = $competition->stages->map(function ($stage) {
            $matches = $stage->matches
                ->sortBy(['round_number', 'sequence'])
                ->values()
                ->map(function ($match) {
                    return [
                        'id' => $match->id,
                        'round_number' => $match->round_number,
                        'sequence' => $match->sequence,
                        'status' => $match->status->value,
                        'bracket_side' => $match->settings['bracket_side'] ?? null,
                        'is_ready' => $match->status === MatchStatus::Pending
                            && $match->matchParticipants->count() === 2,
                        'winner_participant_id' => $match->winner_participant_id,
                        'participants' => $match->matchParticipants
                            ->sortBy('slot')
                            ->values()
                            ->map(fn ($mp) => [
                                'slot' => $mp->slot,
                                'score' => $mp->score,
                                'result' => $mp->result?->value,
                                'competition_participant_id' => $mp->competition_participant_id,
                                'name' => $mp->competitionParticipant?->participant?->name
                                    ?? "Seed #{$mp->competitionParticipant?->seed}",
                            ]),
                    ];
                });

            return [
                'id' => $stage->id,
                'name' => $stage->name,
                'stage_type' => $stage->stage_type->value,
                'status' => $stage->status->value,
                'matches' => $matches,
            ];
        });

        return Inertia::render('Competitions/Show', [
            'competition' => [
                'id' => $competition->id,
                'name' => $competition->name,
                'slug' => $competition->slug,
                'type' => $competition->type->value,
                'status' => $competition->status->value,
                'starts_at' => $competition->starts_at?->toISOString(),
                'ends_at' => $competition->ends_at?->toISOString(),
            ],
            'stages' => $stages,
        ]);
    }
}
