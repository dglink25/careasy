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
            if (Schema::hasTable('abonnements') && Schema::hasColumn('abonnements', 'plan_id')) {

                // Supprimer la clé étrangère avant modification
                Schema::table('abonnements', function (Blueprint $table) {
                    $table->dropForeign(['plan_id']);
                });

                // Modifier la colonne pour qu'elle devienne nullable
                Schema::table('abonnements', function (Blueprint $table) {
                    $table->foreignId('plan_id')->nullable()->change();
                });

                // Réappliquer la contrainte étrangère
                Schema::table('abonnements', function (Blueprint $table) {
                    $table->foreign('plan_id')->references('id')->on('plans')->onDelete('set null');
                });

            }
        } catch (\Exception $e) {
            Log::error('Erreur migration nullable plan_id: '.$e->getMessage());
        }
    }

    public function down()
    {
        try {
            if (Schema::hasTable('abonnements') && Schema::hasColumn('abonnements', 'plan_id')) {

                // Supprimer la clé étrangère avant modification
                Schema::table('abonnements', function (Blueprint $table) {
                    $table->dropForeign(['plan_id']);
                });

                // Rendre la colonne non nullable
                Schema::table('abonnements', function (Blueprint $table) {
                    $table->foreignId('plan_id')->nullable(false)->change();
                });

                // Réappliquer la contrainte étrangère
                Schema::table('abonnements', function (Blueprint $table) {
                    $table->foreign('plan_id')->references('id')->on('plans')->onDelete('cascade');
                });

            }
        } catch (\Exception $e) {
            Log::error('Erreur rollback nullable plan_id: '.$e->getMessage());
        }
    }
};