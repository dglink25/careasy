<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up()
    {
        try {
            if (Schema::hasTable('entreprises')) {

                Schema::table('entreprises', function (Blueprint $table) {

                    if (!Schema::hasColumn('entreprises', 'trial_starts_at')) {
                        $table->timestamp('trial_starts_at')->nullable()->after('status');
                    }

                    if (!Schema::hasColumn('entreprises', 'trial_ends_at')) {
                        $table->timestamp('trial_ends_at')->nullable()->after('trial_starts_at');
                    }

                    if (!Schema::hasColumn('entreprises', 'has_used_trial')) {
                        $table->boolean('has_used_trial')->default(false)->after('trial_ends_at');
                    }

                    if (!Schema::hasColumn('entreprises', 'max_services_allowed')) {
                        $table->integer('max_services_allowed')->default(0)->after('has_used_trial');
                    }

                    if (!Schema::hasColumn('entreprises', 'max_employees_allowed')) {
                        $table->integer('max_employees_allowed')->default(0)->after('max_services_allowed');
                    }

                    if (!Schema::hasColumn('entreprises', 'has_api_access')) {
                        $table->boolean('has_api_access')->default(false)->after('max_employees_allowed');
                    }
                });
            }

        } catch (\Exception $e) {
            Log::error('Erreur migration entreprises: '.$e->getMessage());
        }
    }

    public function down()
    {
        try {
            Schema::table('entreprises', function (Blueprint $table) {

                $columns = [
                    'trial_starts_at',
                    'trial_ends_at',
                    'has_used_trial',
                    'max_services_allowed',
                    'max_employees_allowed',
                    'has_api_access'
                ];

                foreach ($columns as $column) {
                    if (Schema::hasColumn('entreprises', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });

        } catch (\Exception $e) {
            Log::error('Erreur rollback migration entreprises: '.$e->getMessage());
        }
    }
};