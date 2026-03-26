<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration{
    public function up(): void{
        Schema::create('password_reset_otps', function (Blueprint $table) {
            $table->id();
            $table->string('identifier');          // email ou phone
            $table->enum('identifier_type', ['email', 'phone']);
            $table->string('code', 6);              // OTP 6 chiffres
            $table->boolean('used')->default(false);
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0); // tentatives de saisie
            $table->timestamps();

            $table->index(['identifier', 'identifier_type']);
        });
    }

    public function down(): void{
        Schema::dropIfExists('password_reset_otps');
    }
};