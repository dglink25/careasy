<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration{
    public function up(): void
    {
        Schema::create('qr_login_tokens', function (Blueprint $table) {
            $table->id();

            // Lien vers l'utilisateur propriétaire (celui qui génère le QR)
            $table->foreignId('user_id')
                  ->constrained()
                  ->onDelete('cascade');

            // Token unique aléatoire (64 caractères hex)
            $table->string('token', 64)->unique();

            // Statut : pending → used | expired
            $table->enum('status', ['pending', 'used', 'expired'])
                  ->default('pending');

            // Expiration (défaut : 2 minutes après création)
            $table->timestamp('expires_at');

            // Infos sur l'appareil qui a scanné (renseignées lors du scan)
            $table->string('used_by_device', 255)->nullable();
            $table->string('used_by_ip', 45)->nullable();
            $table->timestamp('used_at')->nullable();

            // Token Sanctum créé pour le nouvel appareil après scan réussi
            $table->text('new_auth_token')->nullable();

            $table->timestamps();

            // Index pour les lookups fréquents
            $table->index(['token', 'status']);
            $table->index(['user_id', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_login_tokens');
    }
};