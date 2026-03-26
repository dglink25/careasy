<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('qr_login_tokens')) {

            Schema::create('qr_login_tokens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('token', 64)->unique();
                $table->enum('status', ['pending', 'used', 'expired'])->default('pending');
                $table->timestamp('expires_at');
                $table->string('used_by_device', 255)->nullable();
                $table->string('used_by_ip', 45)->nullable();
                $table->string('used_sanctum_token')->nullable();
                $table->timestamp('used_at')->nullable();
                $table->timestamps();

                $table->index(['token', 'status']);
                $table->index(['user_id', 'status']);
                $table->index('expires_at');
            });

        } else {

            Schema::table('qr_login_tokens', function (Blueprint $table) {

                if (!Schema::hasColumn('qr_login_tokens', 'user_id')) {
                    $table->foreignId('user_id')->constrained()->onDelete('cascade');
                }

                if (!Schema::hasColumn('qr_login_tokens', 'token')) {
                    $table->string('token', 64)->unique();
                }

                if (!Schema::hasColumn('qr_login_tokens', 'status')) {
                    $table->enum('status', ['pending', 'used', 'expired'])->default('pending');
                }

                if (!Schema::hasColumn('qr_login_tokens', 'expires_at')) {
                    $table->timestamp('expires_at');
                }

                if (!Schema::hasColumn('qr_login_tokens', 'used_by_device')) {
                    $table->string('used_by_device', 255)->nullable();
                }

                if (!Schema::hasColumn('qr_login_tokens', 'used_by_ip')) {
                    $table->string('used_by_ip', 45)->nullable();
                }

                if (!Schema::hasColumn('qr_login_tokens', 'used_sanctum_token')) {
                    $table->string('used_sanctum_token')->nullable();
                }

                if (!Schema::hasColumn('qr_login_tokens', 'used_at')) {
                    $table->timestamp('used_at')->nullable();
                }

                if (!Schema::hasColumn('qr_login_tokens', 'created_at') &&
                    !Schema::hasColumn('qr_login_tokens', 'updated_at')) {
                    $table->timestamps();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_login_tokens');
    }
};