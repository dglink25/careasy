<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up()
    {
        try {
            if (Schema::hasTable('abonnements') && Schema::hasColumn('abonnements', 'paiement_id')) {

                // Supprimer la contrainte étrangère si elle existe
                Schema::table('abonnements', function (Blueprint $table) {
                    $table->dropForeign(['paiement_id']);
                });

                // Modifier la colonne pour la rendre nullable
                Schema::table('abonnements', function (Blueprint $table) {
                    $table->foreignId('paiement_id')->nullable()->change();
                });

                // Réappliquer la contrainte étrangère
                Schema::table('abonnements', function (Blueprint $table) {
                    $table->foreign('paiement_id')->references('id')->on('paiements')->onDelete('set null');
                });

            }
        } catch (\Exception $e) {
            Log::error('Erreur migration nullable paiement_id: '.$e->getMessage());
        }
    }

    public function down()
    {
        try {
            if (Schema::hasTable('abonnements') && Schema::hasColumn('abonnements', 'paiement_id')) {

                // Supprimer la contrainte étrangère
                Schema::table('abonnements', function (Blueprint $table) {
                    $table->dropForeign(['paiement_id']);
                });

                // Rendre la colonne non nullable
                Schema::table('abonnements', function (Blueprint $table) {
                    $table->foreignId('paiement_id')->nullable(false)->change();
                });

                // Réappliquer la contrainte étrangère
                Schema::table('abonnements', function (Blueprint $table) {
                    $table->foreign('paiement_id')->references('id')->on('paiements')->onDelete('cascade');
                });

            }
        } catch (\Exception $e) {
            Log::error('Erreur rollback nullable paiement_id: '.$e->getMessage());
        }
    }
};