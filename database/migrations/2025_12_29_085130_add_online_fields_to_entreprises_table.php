<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('entreprises', function (Blueprint $t) {
            $t->boolean('status_online')->default(false)->after('status');
            $t->string('whatsapp_phone', 25)->nullable()->after('google_formatted_address');
            $t->string('call_phone', 25)->nullable()->after('whatsapp_phone');
        });
    }

    public function down(): void {
        Schema::table('entreprises', function (Blueprint $t) {
            $t->dropColumn(['status_online','whatsapp_phone','call_phone']);
        });
    }
};