<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_tokens', function (Blueprint $table) {
            $table->string('webhook_url')->nullable()->after('plain_text_prefix');
            $table->string('webhook_secret')->nullable()->after('webhook_url');
        });
    }

    public function down(): void
    {
        Schema::table('api_tokens', function (Blueprint $table) {
            $table->dropColumn(['webhook_url', 'webhook_secret']);
        });
    }
};
