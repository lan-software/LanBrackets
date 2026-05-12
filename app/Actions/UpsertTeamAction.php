<?php

namespace App\Actions;

use App\Models\Team;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Idempotently upsert a team identified by `(source_system, external_reference_id)`.
 *
 * The action is invoked by `TeamApiController::upsert` to satisfy the LanCore→LanBrackets
 * team-identity bridge contract. Race-tolerant: a concurrent insert on the same source
 * tuple causes a single retry; a slug collision causes a single retry with a random
 * suffix appended to the slug.
 *
 * @see docs/mil-std-498/SRS.md COMP-F-018
 * @see docs/mil-std-498/IDD.md §3.8.1
 */
class UpsertTeamAction
{
    /**
     * @param  array{name: string, tag?: ?string, description?: ?string, status?: ?string, external_reference_id: string, source_system: string}  $attrs
     */
    public function execute(array $attrs): Team
    {
        return DB::transaction(function () use ($attrs): Team {
            $attrs['slug'] = Str::slug($attrs['name']);

            try {
                return Team::updateOrCreate(
                    [
                        'source_system' => $attrs['source_system'],
                        'external_reference_id' => $attrs['external_reference_id'],
                    ],
                    $attrs,
                );
            } catch (UniqueConstraintViolationException $e) {
                if (str_contains($e->getMessage(), 'teams_slug_unique')) {
                    $attrs['slug'] = Str::slug($attrs['name']).'-'.Str::random(6);

                    return Team::updateOrCreate(
                        [
                            'source_system' => $attrs['source_system'],
                            'external_reference_id' => $attrs['external_reference_id'],
                        ],
                        $attrs,
                    );
                }

                return Team::updateOrCreate(
                    [
                        'source_system' => $attrs['source_system'],
                        'external_reference_id' => $attrs['external_reference_id'],
                    ],
                    $attrs,
                );
            }
        });
    }
}
