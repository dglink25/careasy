<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration{
    public function up() {
        Schema::table('entreprises', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->constrained('plans')->onDelete('set null');
            $table->timestamp('plan_expires_at')->nullable();
            $table->string('plan_status')->default('inactive'); 
            $table->timestamp('plan_subscribed_at')->nullable();
            $table->timestamp('plan_cancelled_at')->nullable();
        });
    }

    public function down() {
        Schema::table('entreprises', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
            $table->dropColumn([
                'plan_id',
                'plan_expires_at',
                'plan_status',
                'plan_subscribed_at',
                'plan_cancelled_at'
            ]);
        });
    }
};