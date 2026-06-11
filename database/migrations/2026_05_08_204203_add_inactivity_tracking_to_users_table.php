<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void{
        Schema::table('users', function (Blueprint $table) {

            if (!Schema::hasColumn('users', 'last_activity_at')) {
                $table->timestamp('last_activity_at')
                    ->nullable()
                    ->after('last_seen_at');
            }

            if (!Schema::hasColumn('users', 'last_inactivity_reminder_at')) {
                $table->timestamp('last_inactivity_reminder_at')
                    ->nullable()
                    ->after('last_activity_at');
            }

            if (!Schema::hasColumn('users', 'inactivity_reminder_count')) {
                $table->unsignedTinyInteger('inactivity_reminder_count')
                    ->default(0)
                    ->after('last_inactivity_reminder_at');
            }

            if (!Schema::hasColumn('users', 'activity_status')) {
                $table->enum('activity_status', [
                    'active',
                    'inactive',
                    'suspended'
                ])->default('active')
                  ->after('inactivity_reminder_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {

            $columns = [
                'last_activity_at',
                'last_inactivity_reminder_at',
                'inactivity_reminder_count',
                'activity_status',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};