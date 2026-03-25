<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_stage_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('round_number')->nullable();
            $table->unsignedInteger('sequence')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->foreignId('winner_participant_id')->nullable()->constrained('competition_participants')->nullOnDelete();
            $table->foreignId('loser_participant_id')->nullable()->constrained('competition_participants')->nullOnDelete();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['competition_id', 'competition_stage_id']);
            $table->index(['competition_stage_id', 'round_number', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
