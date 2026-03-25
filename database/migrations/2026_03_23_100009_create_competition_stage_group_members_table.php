<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_stage_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_stage_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_participant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('seed')->nullable();
            $table->timestamps();

            $table->unique(
                ['competition_stage_group_id', 'competition_participant_id'],
                'csgm_group_participant_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_stage_group_members');
    }
};
