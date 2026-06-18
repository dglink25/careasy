<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration{
    public function up(): void {
        // ── 1. Nettoyage des doublons ───────────────────────────────
        $duplicates = DB::select("
            SELECT MIN(id) AS keep_id,
                   LEAST(user_one_id, user_two_id)    AS u1,
                   GREATEST(user_one_id, user_two_id) AS u2,
                   service_id
            FROM conversations
            WHERE service_id IS NOT NULL
            GROUP BY u1, u2, service_id
            HAVING COUNT(*) > 1
        ");

        foreach ($duplicates as $row) {
            DB::table('conversations')
                ->whereRaw('LEAST(user_one_id, user_two_id) = ?', [$row->u1])
                ->whereRaw('GREATEST(user_one_id, user_two_id) = ?', [$row->u2])
                ->where('service_id', $row->service_id)
                ->where('id', '!=', $row->keep_id)
                ->delete();
        }

        // ── 2. Ajouter la contrainte unique ─────────────────────────
        Schema::table('conversations', function (Blueprint $table) {
            $table->unique(
                ['user_one_id', 'user_two_id', 'service_id'],
                'conversations_unique_per_service'
            );
        });
    }

    public function down(): void {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropUnique('conversations_unique_per_service');
        });
    }
};