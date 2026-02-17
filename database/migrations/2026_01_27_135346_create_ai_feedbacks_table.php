<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
            $table->tinyInteger('rating')->comment('1 à 5');
            $table->text('comment')->nullable();
            $table->boolean('is_helpful')->default(false);
            $table->string('language_detected')->nullable();
            $table->string('domain_detected')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_feedbacks');
    }
};
