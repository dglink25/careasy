<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration{
    public function up(){
        Schema::create('abonnements', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('plan_id')->constrained()->onDelete('cascade');
            $table->foreignId('paiement_id')->constrained()->onDelete('cascade');
            $table->foreignId('entreprise_id')->nullable()->constrained()->onDelete('set null');
            $table->dateTime('date_debut');
            $table->dateTime('date_fin');
            $table->string('statut')->default('actif'); // actif, expire, annule, suspendu
            $table->boolean('renouvellement_auto')->default(false);
            $table->timestamp('date_annulation')->nullable();
            $table->string('motif_annulation')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['user_id', 'statut']);
            $table->index('reference');
            $table->index('date_fin');
        });
    }

    public function down()
    {
        Schema::dropIfExists('abonnements');
    }
};