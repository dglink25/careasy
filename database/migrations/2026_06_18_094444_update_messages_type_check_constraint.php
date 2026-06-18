<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration{
    public function up(): void  {
        DB::statement("
            ALTER TABLE messages
            DROP CONSTRAINT IF EXISTS messages_type_check
        ");

        DB::statement("
            ALTER TABLE messages
            ADD CONSTRAINT messages_type_check
            CHECK (
                type IN (
                    'text',
                    'image',
                    'video',
                    'vocal',
                    'document'
                )
            )
        ");
    }

    public function down(): void {
        DB::statement("
            ALTER TABLE messages
            DROP CONSTRAINT IF EXISTS messages_type_check
        ");

        DB::statement("
            ALTER TABLE messages
            ADD CONSTRAINT messages_type_check
            CHECK (
                type IN (
                    'text',
                    'image',
                    'video',
                    'vocal'
                )
            )
        ");
    }
};
