<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration{
    public function up()
    {
        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('plan_id')->constrained()->onDelete('cascade');
            $table->decimal('montant', 10, 2);
            $table->string('devise')->default('XOF');
            $table->string('methode_paiement')->nullable(); // fedapay, orange_money, etc.
            $table->string('statut')->default('en_attente'); // en_attente, succes, echec, rembourse
            $table->json('fedapay_response')->nullable();
            $table->string('fedapay_transaction_id')->nullable();
            $table->string('fedapay_status')->nullable();
            $table->timestamp('date_paiement')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'statut']);
            $table->index('reference');
            $table->index('fedapay_transaction_id');
        });
    }

    public function down() {
        Schema::dropIfExists('paiements');
    }
};