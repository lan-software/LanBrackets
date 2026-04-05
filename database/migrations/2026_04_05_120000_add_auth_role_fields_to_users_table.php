<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role')->default(UserRole::User->value)->after('password');
            $table->boolean('external')->default(false)->after('role');
            $table->string('external_provider')->nullable()->after('external');
            $table->string('external_id')->nullable()->after('external_provider');
            $table->unique(['external_provider', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['external_provider', 'external_id']);
            $table->dropColumn(['role', 'external', 'external_provider', 'external_id']);
        });
    }
};
