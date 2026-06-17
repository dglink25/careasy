<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Index 1
        DB::statement("
            CREATE INDEX IF NOT EXISTS messages_conv_created_idx
            ON messages (conversation_id, created_at)
        ");

        // Index 2
        DB::statement("
            CREATE INDEX IF NOT EXISTS messages_conv_sender_read_idx
            ON messages (conversation_id, sender_id, read_at)
        ");

        // Index 3
        DB::statement("
            CREATE INDEX IF NOT EXISTS messages_sender_idx
            ON messages (sender_id)
        ");
    }

    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS messages_conv_created_idx");
        DB::statement("DROP INDEX IF EXISTS messages_conv_sender_read_idx");
        DB::statement("DROP INDEX IF EXISTS messages_sender_idx");
    }
};