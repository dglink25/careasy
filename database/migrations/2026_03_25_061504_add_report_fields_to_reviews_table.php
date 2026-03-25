<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void{
        Schema::table('reviews', function (Blueprint $table) {

            // ✅ Vérifier avant ajout (sécurité)
            if (!Schema::hasColumn('reviews', 'report_reason')) {
                $table->text('report_reason')->nullable()->after('reported');
            }

            if (!Schema::hasColumn('reviews', 'reported_at')) {
                $table->timestamp('reported_at')->nullable()->after('report_reason');
            }

            // ✅ Index pour performance (filtrer les signalements)
            if (!Schema::hasColumn('reviews', 'reported_at_index')) {
                $table->index('reported_at');
            }
        });
    }

    public function down(): void {
        Schema::table('reviews', function (Blueprint $table) {

            if (Schema::hasColumn('reviews', 'report_reason')) {
                $table->dropColumn('report_reason');
            }

            if (Schema::hasColumn('reviews', 'reported_at')) {
                $table->dropColumn('reported_at');
            }

            // Supprimer index si existe
            try {
                $table->dropIndex(['reported_at']);
            } catch (\Exception $e) {
                // éviter crash si index absent
            }
        });
    }
};