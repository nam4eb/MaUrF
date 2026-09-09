<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->index(['user_id', 'source_import_id'], 'conversations_user_import_idx');
        });
        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->index('social_person_id', 'participants_person_idx');
        });
        Schema::table('messages', function (Blueprint $table) {
            $table->index(['user_id', 'sent_at'], 'messages_user_sent_idx');
            $table->index(['sender_person_id', 'sent_at'], 'messages_sender_sent_idx');
            $table->index('source_import_id', 'messages_import_idx');
        });
        Schema::table('friendships', function (Blueprint $table) {
            $table->index('source_import_id', 'friendships_import_idx');
        });
        Schema::table('facebook_interactions', function (Blueprint $table) {
            $table->index('source_import_id', 'facebook_interactions_import_idx');
            $table->index(['user_id', 'target_person_id', 'occurred_at'], 'facebook_target_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', fn (Blueprint $table) => $table->dropIndex('conversations_user_import_idx'));
        Schema::table('conversation_participants', fn (Blueprint $table) => $table->dropIndex('participants_person_idx'));
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_user_sent_idx');
            $table->dropIndex('messages_sender_sent_idx');
            $table->dropIndex('messages_import_idx');
        });
        Schema::table('friendships', fn (Blueprint $table) => $table->dropIndex('friendships_import_idx'));
        Schema::table('facebook_interactions', function (Blueprint $table) {
            $table->dropIndex('facebook_interactions_import_idx');
            $table->dropIndex('facebook_target_date_idx');
        });
    }
};
