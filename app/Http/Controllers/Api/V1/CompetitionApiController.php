<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\AddCompetitionParticipantAction;
use App\Actions\CreateCompetitionAction;
use App\Actions\GenerateBracketAction;
use App\Actions\ReportMatchResultAction;
use App\Enums\CompetitionType;
use App\Enums\ParticipantType;
use App\Enums\StageType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AddParticipantRequest;
use App\Http\Requests\Api\V1\ReportResultRequest;
use App\Http\Requests\Api\V1\StoreCompetitionRequest;
use App\Http\Resources\V1\CompetitionMatchResource;
use App\Http\Resources\V1\CompetitionParticipantResource;
use App\Http\Resources\V1\CompetitionResource;
use App\Http\Resources\V1\CompetitionStageResource;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\CompetitionStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CompetitionApiController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $competitions = Competition::query()
            ->withCount(['participants', 'stages'])
            ->orderByDesc('created_at')
            ->paginate(25);

        return CompetitionResource::collection($competitions);
    }

    public function store(
        StoreCompetitionRequest $request,
        CreateCompetitionAction $action,
    ): CompetitionResource {
        $competition = $action->execute(
            name: $request->validated('name'),
            type: CompetitionType::from($request->validated('type')),
            stageType: StageType::from($request->validated('stage_type')),
            options: array_filter([
                'description' => $request->validated('description'),
                'settings' => $request->validated('settings'),
            ]),
        );

        return new CompetitionResource($competition->loadCount(['participants', 'stages']));
    }

    public function show(Competition $competition): CompetitionResource
    {
        return new CompetitionResource(
            $competition->load(['stages', 'participants.participant'])
                ->loadCount(['participants', 'stages'])
        );
    }

    public function stages(Competition $competition): AnonymousResourceCollection
    {
        return CompetitionStageResource::collection(
            $competition->stages()->withCount('matches')->get()
        );
    }

    public function addParticipant(
        Competition $competition,
        AddParticipantRequest $request,
        AddCompetitionParticipantAction $action,
    ): JsonResponse {
        $type = ParticipantType::from($request->validated('participant_type'));
        $model = $type->modelClass()::findOrFail($request->validated('participant_id'));

        $participant = $action->execute(
            competition: $competition,
            participant: $model,
            seed: $request->validated('seed'),
        );

        return (new CompetitionParticipantResource($participant->load('participant')))
            ->response()
            ->setStatusCode(201);
    }

    public function generate(
        Competition $competition,
        CompetitionStage $stage,
        GenerateBracketAction $action,
    ): JsonResponse {
        $action->execute($stage);

        return response()->json([
            'message' => 'Bracket generated successfully.',
            'stage' => new CompetitionStageResource($stage->loadCount('matches')),
        ]);
    }

    public function matches(
        Competition $competition,
        CompetitionStage $stage,
    ): AnonymousResourceCollection {
        $matches = $stage->matches()
            ->with('matchParticipants')
            ->orderBy('round_number')
            ->orderBy('sequence')
            ->get();

        return CompetitionMatchResource::collection($matches);
    }

    public function reportResult(
        Competition $competition,
        CompetitionMatch $match,
        ReportResultRequest $request,
        ReportMatchResultAction $action,
    ): CompetitionMatchResource {
        $action->execute($match, $request->validated('scores'));

        return new CompetitionMatchResource($match->fresh()->load('matchParticipants'));
    }

    public function standings(Competition $competition): JsonResponse
    {
        $stages = $competition->stages()->with(['matches.matchParticipants'])->get();
        $participants = $competition->participants()->with('participant')->get();

        $standings = $participants->map(function ($participant) use ($stages) {
            $wins = 0;
            $losses = 0;
            $draws = 0;

            foreach ($stages as $stage) {
                foreach ($stage->matches as $match) {
                    if ($match->winner_participant_id === $participant->id) {
                        $wins++;
                    } elseif ($match->loser_participant_id === $participant->id) {
                        $losses++;
                    } elseif ($match->matchParticipants->contains('competition_participant_id', $participant->id)
                        && $match->matchParticipants->where('competition_participant_id', $participant->id)->first()?->result?->value === 'draw') {
                        $draws++;
                    }
                }
            }

            return [
                'participant_id' => $participant->id,
                'participant_name' => $participant->participant?->name,
                'seed' => $participant->seed,
                'wins' => $wins,
                'losses' => $losses,
                'draws' => $draws,
            ];
        })->sortByDesc('wins')->values();

        return response()->json(['data' => $standings]);
    }
}
