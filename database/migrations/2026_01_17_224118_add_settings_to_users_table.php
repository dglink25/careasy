<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('theme')->nullable()->after('role');
            $table->string('profile_photo_path')->nullable()->after('theme');
            $table->json('settings')->nullable()->after('profile_photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['theme', 'profile_photo_path', 'settings']);
        });
    }
};