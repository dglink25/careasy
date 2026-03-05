<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration{
    public function up() {
        Schema::table('conversations', function (Blueprint $table) {
            $table->unsignedBigInteger('service_id')->nullable()->after('user_two_id');
            $table->string('service_name')->nullable()->after('service_id');
            $table->string('entreprise_name')->nullable()->after('service_name');
            
            $table->foreign('service_id')->references('id')->on('services')->onDelete('set null');
        });
    }

    public function down() {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
            $table->dropColumn(['service_id', 'service_name', 'entreprise_name']);
        });
    }
};