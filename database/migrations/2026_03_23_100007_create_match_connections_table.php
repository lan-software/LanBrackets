<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_match_id')->constrained('matches')->cascadeOnDelete();
            $table->string('source_outcome'); // 'winner', 'loser', 'placement_1', 'placement_2', etc.
            $table->foreignId('target_match_id')->constrained('matches')->cascadeOnDelete();
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
