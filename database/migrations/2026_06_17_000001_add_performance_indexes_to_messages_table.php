<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // Éviter les doublons si les index existent déjà (environnements existants)
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexes = array_keys($sm->listTableIndexes('messages'));

            if (!in_array('messages_conv_created_idx', $indexes)) {
                $table->index(['conversation_id', 'created_at'], 'messages_conv_created_idx');
            }

            if (!in_array('messages_conv_sender_read_idx', $indexes)) {
                $table->index(['conversation_id', 'sender_id', 'read_at'], 'messages_conv_sender_read_idx');
            }

            if (!in_array('messages_sender_idx', $indexes)) {
                $table->index('sender_id', 'messages_sender_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_conv_created_idx');
            $table->dropIndex('messages_conv_sender_read_idx');
            $table->dropIndex('messages_sender_idx');
        });
    }
};
