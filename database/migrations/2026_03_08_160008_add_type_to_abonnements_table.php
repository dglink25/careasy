<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

return new class extends Migration{
    public function up() {
        try {

            if (Schema::hasTable('abonnements')) {

                Schema::table('abonnements', function (Blueprint $table) {

                    if (!Schema::hasColumn('abonnements', 'type')) {
                        $table->string('type')->default('standard')->after('reference');
                    }

                    if (!Schema::hasColumn('abonnements', 'entreprise_id')) {
                        $table->foreignId('entreprise_id')
                              ->nullable()
                              ->constrained()
                              ->after('user_id');
                    }

                });

            }

        } catch (\Exception $e) {
            Log::error('Erreur migration abonnements: '.$e->getMessage());
        }
    }

    public function down(){
        try {

            Schema::table('abonnements', function (Blueprint $table) {

                if (Schema::hasColumn('abonnements', 'entreprise_id')) {
                    $table->dropForeign(['entreprise_id']);
                    $table->dropColumn('entreprise_id');
                }

                if (Schema::hasColumn('abonnements', 'type')) {
                    $table->dropColumn('type');
                }

            });

        } catch (\Exception $e) {
            Log::error('Erreur rollback abonnements: '.$e->getMessage());
        }
    }
};