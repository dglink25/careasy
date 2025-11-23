<?php
// database/migrations/2025_01_02_000000_create_entreprises_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('entreprises', function (Blueprint $table) {
            $table->id();

            $table->string('name');            
            $table->foreignId('prestataire_id')->constrained('users')->onDelete('cascade');

            $table->string('ifu_number');
            $table->string('ifu_file');

            $table->string('rccm_number');
            $table->string('rccm_file');

            $table->string('pdg_full_name');
            $table->string('pdg_full_profession');

            $table->string('role_user'); // Exemple : PDG, Directeur, etc.
            $table->string('siege')->nullable();

            $table->string('logo')->nullable();

            $table->string('certificate_number');
            $table->string('certificate_file');

            $table->string('image_boutique')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('entreprises');
    }
};
