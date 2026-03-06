<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration{
    public function up(){
        Schema::create('rendez_vous', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->onDelete('cascade');
            $table->foreignId('client_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('prestataire_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('entreprise_id')->constrained()->onDelete('cascade');
            
            // Date et heure du rendez-vous
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            
            // Statut du rendez-vous
            $table->enum('status', [
                'pending',      // En attente de confirmation
                'confirmed',    // Confirmé par le prestataire
                'cancelled',    // Annulé
                'completed',    // Terminé
                'rescheduled'   // Reporté
            ])->default('pending');
            
            // Informations complémentaires
            $table->text('client_notes')->nullable();
            $table->text('prestataire_notes')->nullable();
            
            // Horodatage des actions
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            
            // Rappels
            $table->boolean('reminder_sent_client')->default(false);
            $table->boolean('reminder_sent_prestataire')->default(false);
            $table->timestamp('last_reminder_sent_at')->nullable();
            
            $table->timestamps();
            
            // Index pour optimiser les recherches
            $table->index(['service_id', 'date', 'start_time']);
            $table->index(['prestataire_id', 'status']);
            $table->index(['client_id', 'status']);
            $table->index(['date', 'status']);
        });
    }

    public function down() {
        Schema::dropIfExists('rendez_vous');
    }
};