<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration{
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
            // Date de la dernière activité réelle (connexion, action, etc.)
            $table->timestamp('last_activity_at')->nullable()->after('last_seen_at');

            // Date du dernier rappel d'inactivité envoyé
            $table->timestamp('last_inactivity_reminder_at')->nullable()->after('last_activity_at');

            // Nombre de rappels envoyés (évite le spam)
            $table->unsignedTinyInteger('inactivity_reminder_count')->default(0)->after('last_inactivity_reminder_at');

            // Statut : active | inactive | suspended
            $table->enum('activity_status', ['active', 'inactive', 'suspended'])
                  ->default('active')
                  ->after('inactivity_reminder_count');
        });
    }

    public function down(): void{
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'last_activity_at',
                'last_inactivity_reminder_at',
                'inactivity_reminder_count',
                'activity_status',
            ]);
        });
    }
};