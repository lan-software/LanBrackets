<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\AddCompetitionParticipantAction;
use App\Actions\CompleteStageAction;
use App\Actions\CreateCompetitionAction;
use App\Actions\GenerateBracketAction;
use App\Actions\ReportMatchResultAction;
use App\Enums\CompetitionStatus;
use App\Enums\CompetitionType;
use App\Enums\MatchStatus;
use App\Enums\ParticipantStatus;
use App\Enums\ParticipantType;
use App\Enums\StageStatus;
use App\Enums\StageType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AddParticipantRequest;
use App\Http\Requests\Api\V1\BulkAddParticipantsRequest;
use App\Http\Requests\Api\V1\ReportResultRequest;
use App\Http\Requests\Api\V1\StoreCompetitionRequest;
use App\Http\Requests\Api\V1\UpdateCompetitionRequest;
use App\Http\Resources\V1\CompetitionMatchResource;
use App\Http\Resources\V1\CompetitionParticipantResource;
use App\Http\Resources\V1\CompetitionResource;
use App\Http\Resources\V1\CompetitionStageResource;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\CompetitionParticipant;
use App\Models\CompetitionStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CompetitionApiController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $competitions = Competition::query()
            ->when($request->query('external_reference_id'), fn ($q, $v) => $q->where('external_reference_id', $v))
            ->when($request->query('source_system'), fn ($q, $v) => $q->where('source_system', $v))
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
                'external_reference_id' => $request->validated('external_reference_id'),
                'source_system' => $request->validated('source_system'),
                'metadata' => $request->validated('metadata'),
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

    public function update(
        Competition $competition,
        UpdateCompetitionRequest $request,
    ): CompetitionResource {
        $validated = $request->validated();

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $competition->update($validated);

        return new CompetitionResource($competition->fresh()->loadCount(['participants', 'stages']));
    }

    public function destroy(Competition $competition): JsonResponse
    {
        $deletableStatuses = [CompetitionStatus::Draft, CompetitionStatus::Archived];

        if (! in_array($competition->status, $deletableStatuses)) {
            return response()->json([
                'message' => 'Only draft or archived competitions can be deleted.',
            ], 422);
        }

        $competition->delete();

        return response()->json(null, 204);
    }

    public function regenerateShareToken(Competition $competition): JsonResponse
    {
        $competition->update(['share_token' => Str::random(32)]);

        return response()->json([
            'share_token' => $competition->share_token,
        ]);
    }

    public function completeStage(
        Competition $competition,
        CompetitionStage $stage,
        CompleteStageAction $action,
    ): JsonResponse {
        $action->execute($stage);

        return response()->json([
            'message' => 'Stage completed successfully.',
            'stage' => new CompetitionStageResource($stage->fresh()->loadCount('matches')),
        ]);
    }

    public function bulkAddParticipants(
        Competition $competition,
        BulkAddParticipantsRequest $request,
        AddCompetitionParticipantAction $action,
    ): JsonResponse {
        $participants = DB::transaction(function () use ($competition, $request, $action) {
            $created = [];

            foreach ($request->validated('participants') as $data) {
                $type = ParticipantType::from($data['participant_type']);
                $model = $type->modelClass()::findOrFail($data['participant_id']);

                $created[] = $action->execute(
                    competition: $competition,
                    participant: $model,
                    seed: $data['seed'] ?? null,
                );
            }

            return $created;
        });

        $participantIds = collect($participants)->pluck('id');
        $loaded = CompetitionParticipant::whereIn('id', $participantIds)->with('participant')->get();

        return response()->json([
            'data' => CompetitionParticipantResource::collection($loaded),
        ], 201);
    }

    public function withdrawParticipant(
        Competition $competition,
        CompetitionParticipant $participant,
    ): JsonResponse {
        $hasRunningStage = $competition->stages()
            ->whereNot('status', StageStatus::Pending)
            ->exists();

        if ($hasRunningStage) {
            return response()->json([
                'message' => 'Cannot withdraw participants after bracket generation. Use disqualification instead.',
            ], 422);
        }

        $participant->update(['status' => ParticipantStatus::Withdrawn]);

        return response()->json(null, 204);
    }

    public function disqualifyParticipant(
        Competition $competition,
        CompetitionParticipant $participant,
    ): JsonResponse {
        $participant->update(['status' => ParticipantStatus::Disqualified]);

        return response()->json([
            'message' => 'Participant disqualified.',
            'data' => new CompetitionParticipantResource($participant->fresh()->load('participant')),
        ]);
    }

    public function cancelMatch(
        Competition $competition,
        CompetitionMatch $match,
    ): CompetitionMatchResource|JsonResponse {
        if ($match->status === MatchStatus::Finished) {
            return response()->json([
                'message' => 'Cannot cancel a finished match.',
            ], 422);
        }

        if ($match->status === MatchStatus::Cancelled) {
            return response()->json([
                'message' => 'Match is already cancelled.',
            ], 422);
        }

        $match->update(['status' => MatchStatus::Cancelled]);

        return new CompetitionMatchResource($match->fresh()->load('matchParticipants'));
    }
}
