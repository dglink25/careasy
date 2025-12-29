<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('messages', function (Blueprint $t) {
            $t->enum('type', ['text','image','video','vocal'])->default('text')->after('read_at');
            $t->string('file_path')->nullable()->after('type');
        });
    }
    public function down(): void {
        Schema::table('messages', function (Blueprint $t) {
            $t->dropColumn(['read_at','type','file_path']);
        });
    }
};