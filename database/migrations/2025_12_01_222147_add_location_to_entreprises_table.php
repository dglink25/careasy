<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration{
    /**
     * Run the migrations.
     */
    public function up(){
        Schema::table('entreprises', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('google_formatted_address')->nullable();
        });
    }

    public function down(){
        Schema::table('entreprises', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'google_formatted_address']);
        });
    }

};
