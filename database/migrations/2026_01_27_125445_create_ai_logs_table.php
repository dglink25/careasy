<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('message_id')->nullable();
            $table->longText('ai_input')->nullable();
            $table->longText('ai_output')->nullable();
            $table->string('detected_intent', 100)->nullable();
            $table->string('detected_language', 10)->nullable();
            $table->decimal('confidence', 4, 2)->nullable();
            $table->string('model_version', 50)->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_logs');
    }
};
