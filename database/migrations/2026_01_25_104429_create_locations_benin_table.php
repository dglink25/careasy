<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration{
    public function up(): void{
            Schema::create('locations_benin', function (Blueprint $table) {
                $table->id();
                $table->string('code_admin')->nullable();
                $table->string('arrondissement');
                $table->string('commune');
                $table->string('departement');
                $table->decimal('latitude', 10, 7);
                $table->decimal('longitude', 10, 7);
                $table->timestamps();
            });
        }

    public function down(): void{
        Schema::dropIfExists('locations_benin');
    }
};
