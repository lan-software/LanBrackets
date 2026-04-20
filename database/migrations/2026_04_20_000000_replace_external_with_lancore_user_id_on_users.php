<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['external_provider', 'external_id']);
            $table->dropColumn(['external', 'external_provider', 'external_id']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('lancore_user_id')->nullable()->unique()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['lancore_user_id']);
            $table->dropColumn('lancore_user_id');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('external')->default(false)->after('role');
            $table->string('external_provider')->nullable()->after('external');
            $table->string('external_id')->nullable()->after('external_provider');
            $table->unique(['external_provider', 'external_id']);
        });
    }
};
