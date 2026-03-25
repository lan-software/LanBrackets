<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_stage_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_stage_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->unsignedInteger('sequence')->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['competition_stage_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_stage_groups');
    }
};
