<?php
// database/migrations/xxxx_xx_xx_xxxxxx_add_promo_fields_to_services_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration{
    public function up(){
        Schema::table('services', function (Blueprint $table) {
            $table->decimal('price_promo', 10, 2)->nullable()->after('price');
            $table->boolean('is_price_on_request')->default(false)->after('price_promo');
            $table->boolean('has_promo')->default(false)->after('is_price_on_request');
            $table->timestamp('promo_start_date')->nullable()->after('has_promo');
            $table->timestamp('promo_end_date')->nullable()->after('promo_start_date');
        });
    }

    public function down()  {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn([
                'price_promo',
                'is_price_on_request',
                'has_promo',
                'promo_start_date',
                'promo_end_date'
            ]);
        });
    }
};