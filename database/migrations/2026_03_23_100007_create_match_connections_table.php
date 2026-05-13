<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_connections', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('source_match_id')->constrained('matches')->cascadeOnDelete();
            $table->string('source_outcome'); // 'winner', 'loser', 'placement_1', 'placement_2', etc.
            $table->foreignUlid('target_match_id')->constrained('matches')->cascadeOnDelete();
            $table->unsignedInteger('target_slot');
            $table->timestamps();

            $table->unique(['source_match_id', 'source_outcome', 'target_match_id'], 'mc_source_outcome_target_unique');
            $table->unique(['target_match_id', 'target_slot'], 'mc_target_slot_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_connections');
    }
};
