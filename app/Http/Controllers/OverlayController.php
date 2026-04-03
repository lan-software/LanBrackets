<?php

namespace App\Http\Controllers;

use App\Enums\MatchStatus;
use App\Models\Competition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class OverlayController extends Controller
{
    public function show(Request $request, Competition $competition): Response
    {
        abort_unless($this->isValidToken($request, $competition), 403);

        $data = $this->buildBracketData($competition);

        return Inertia::render('Overlay/Bracket', [
            'competition' => $data['competition'],
            'stages' => $data['stages'],
            'token' => $request->query('token'),
        ]);
    }

    public function data(Request $request, Competition $competition): JsonResponse
    {
        abort_unless($this->isValidToken($request, $competition), 403);

        return response()->json($this->buildBracketData($competition));
    }

    protected function isValidToken(Request $request, Competition $competition): bool
    {
        $token = $request->query('token');

        return $token !== null
            && $competition->share_token !== null
            && hash_equals($competition->share_token, $token);
    }

    /**
     * @return array{competition: array<string, mixed>, stages: Collection<int, array<string, mixed>>}
     */
    protected function buildBracketData(Competition $competition): array
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

        return [
            'competition' => [
                'id' => $competition->id,
                'name' => $competition->name,
                'slug' => $competition->slug,
                'type' => $competition->type->value,
                'status' => $competition->status->value,
            ],
            'stages' => $stages,
        ];
    }
}
