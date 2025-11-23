<?php
// database/migrations/2025_01_04_000000_create_services_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('services', function (Blueprint $table) {
            $table->id();

            $table->foreignId('entreprise_id')->constrained()->onDelete('cascade');
            $table->foreignId('prestataire_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('domaine_id')->constrained('domaines')->onDelete('cascade');

            $table->string('name');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            $table->decimal('price', 10, 2)->nullable();

            $table->text('descriptions');

            $table->json('medias')->nullable(); // images, vidéos

            $table->boolean('is_open_24h')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('services');
    }
};
