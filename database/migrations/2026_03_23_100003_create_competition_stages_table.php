<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->unsignedInteger('order')->default(0);
            $table->string('stage_type');
            $table->string('status')->default('pending');
            $table->json('settings')->nullable();
            $table->json('progression_meta')->nullable();
            $table->timestamps();

            $table->unique(['competition_id', 'slug']);
            $table->index(['competition_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_stages');
    }
};
