<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\UpsertTeamAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpsertTeamRequest;
use App\Http\Resources\V1\TeamResource;
use App\Models\Team;
use Illuminate\Http\JsonResponse;

/**
 * Exposes team identity endpoints used by LanCore to bridge team identity
 * before invoking bulk participant adds.
 *
 * @see docs/mil-std-498/SRS.md COMP-F-018
 * @see docs/mil-std-498/IDD.md §3.8.1
 */
class TeamApiController extends Controller
{
    public function upsert(UpsertTeamRequest $request, UpsertTeamAction $action): JsonResponse
    {
        $team = $action->execute($request->validated());

        $status = $team->wasRecentlyCreated ? 201 : 200;

        return (new TeamResource($team))
            ->response()
            ->setStatusCode($status);
    }

    public function show(Team $team): TeamResource
    {
        return new TeamResource($team);
    }
}
