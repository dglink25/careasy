<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration{
    public function up(): void
    {
        Schema::create('login_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->boolean('success')->default(false);
            $table->string('ip_address', 45)->nullable();
            $table->string('device', 255)->nullable();
            $table->string('location', 255)->nullable();
            $table->string('method', 50)->default('email'); // email | google | qr | phone
            $table->string('fail_reason', 255)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('success');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_histories');
    }
};