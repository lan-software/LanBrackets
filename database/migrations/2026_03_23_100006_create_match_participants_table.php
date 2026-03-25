<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('competition_participant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('slot');
            $table->integer('score')->nullable();
            $table->string('result')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['match_id', 'slot']);
            $table->unique(['match_id', 'competition_participant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_participants');
    }
};
