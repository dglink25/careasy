<?php

// database/migrations/2025_01_03_000000_create_entreprise_domaine_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('entreprise_domaine', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained()->onDelete('cascade');
            $table->foreignId('domaine_id')->constrained('domaines')->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('entreprise_domaine');
    }
};
