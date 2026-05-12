<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds Postgres partial unique indexes guaranteeing idempotent upserts on
 * `(source_system, external_reference_id)` for both `teams` and `competitions`.
 *
 * @see docs/mil-std-498/SRS.md COMP-F-018, COMP-F-002
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE UNIQUE INDEX teams_source_external_unique ON teams (source_system, external_reference_id) WHERE source_system IS NOT NULL AND external_reference_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX competitions_source_external_unique ON competitions (source_system, external_reference_id) WHERE source_system IS NOT NULL AND external_reference_id IS NOT NULL');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS teams_source_external_unique');
        DB::statement('DROP INDEX IF EXISTS competitions_source_external_unique');
    }
};
