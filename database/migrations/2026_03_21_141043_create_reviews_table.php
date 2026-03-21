<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rendez_vous_id')
                  ->constrained('rendez_vous')
                  ->onDelete('cascade');
            $table->foreignId('client_id')
                  ->constrained('users')
                  ->onDelete('cascade');
            $table->foreignId('prestataire_id')
                  ->constrained('users')
                  ->onDelete('cascade');
            $table->tinyInteger('rating')->unsigned(); // 1 à 5
            $table->text('comment')->nullable();
            $table->boolean('reported')->default(false);
            $table->timestamps();

            $table->unique(['rendez_vous_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};