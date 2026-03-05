<?php
// database/migrations/xxxx_xx_xx_xxxxxx_update_services_table_add_schedule.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up() {
        Schema::table('services', function (Blueprint $table) {
            // Remplacer les champs start_time/end_time par un champ JSON pour les horaires
            $table->json('schedule')->nullable()->after('descriptions');
            $table->boolean('is_always_open')->default(false)->after('schedule');
            
            // Optionnel : rendre les anciens champs nullable pour la transition
            $table->time('start_time')->nullable()->change();
            $table->time('end_time')->nullable()->change();
        });
    }

    public function down() {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['schedule', 'is_always_open']);
            
            // Remettre les contraintes sur les anciens champs
            $table->time('start_time')->nullable(false)->change();
            $table->time('end_time')->nullable(false)->change();
        });
    }
};