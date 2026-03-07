<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique(); // VP1, VP2, VP3, etc.
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2); // Prix en francs CFA
            $table->integer('duration_days'); // Durée en jours
            $table->json('features')->nullable(); // Fonctionnalités incluses
            $table->json('limitations')->nullable(); // Limitations éventuelles
            $table->integer('max_services')->nullable(); // Nombre max de services
            $table->integer('max_employees')->nullable(); // Nombre max d'employés
            $table->boolean('has_priority_support')->default(false);
            $table->boolean('has_analytics')->default(false);
            $table->boolean('has_api_access')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('plans');
    }
};