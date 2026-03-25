<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->string('participant_type');
            $table->unsignedBigInteger('participant_id');
            $table->unsignedInteger('seed')->nullable();
            $table->string('status')->default('registered');
            $table->timestamp('checked_in_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['participant_type', 'participant_id']);
            $table->unique(['competition_id', 'participant_type', 'participant_id'], 'cp_competition_participant_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_participants');
    }
};
