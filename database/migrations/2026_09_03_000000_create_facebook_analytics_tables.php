<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_sessions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('original_filename');
            $t->string('storage_path');
            $t->unsignedBigInteger('file_size');
            $t->string('fingerprint', 64);
            $t->string('status')->default('uploaded')->index();
            $t->unsignedTinyInteger('progress')->default(0);
            $t->unsignedInteger('total_files')->default(0);
            $t->unsignedInteger('processed_files')->default(0);
            $t->unsignedBigInteger('records_processed')->default(0);
            $t->unsignedInteger('warning_count')->default(0);
            $t->json('warnings')->nullable();
            $t->text('error_message')->nullable();
            $t->boolean('privacy_mode')->default(true);
            $t->timestamp('started_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'fingerprint']);
        });
        Schema::create('social_people', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('external_identifier')->nullable();
            $t->string('display_name');
            $t->string('normalized_name')->index();
            $t->string('source')->default('messenger');
            $t->timestamp('first_seen_at')->nullable();
            $t->timestamp('last_seen_at')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'external_identifier']);
            $t->index(['user_id', 'last_seen_at']);
        });
        Schema::create('conversations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('external_identifier')->nullable();
            $t->string('title');
            $t->string('type')->default('unknown');
            $t->unsignedInteger('participant_count')->default(0);
            $t->timestamp('first_message_at')->nullable();
            $t->timestamp('last_message_at')->nullable();
            $t->unsignedBigInteger('message_count')->default(0);
            $t->foreignUuid('source_import_id')->constrained('import_sessions')->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['user_id', 'external_identifier']);
            $t->index(['user_id', 'type']);
        });
        Schema::create('conversation_participants', function (Blueprint $t) {
            $t->foreignUuid('conversation_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('social_person_id')->constrained()->cascadeOnDelete();
            $t->boolean('is_owner')->default(false);
            $t->timestamps();
            $t->primary(['conversation_id', 'social_person_id']);
        });
        Schema::create('messages', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('conversation_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('sender_person_id')->nullable()->constrained('social_people')->nullOnDelete();
            $t->string('source_fingerprint', 64);
            $t->timestamp('sent_at')->index();
            $t->string('message_type')->default('generic');
            $t->boolean('has_text')->default(false);
            $t->boolean('has_media')->default(false);
            $t->boolean('has_reaction')->default(false);
            $t->text('content')->nullable();
            $t->string('content_hash', 64)->nullable();
            $t->json('metadata')->nullable();
            $t->foreignUuid('source_import_id')->constrained('import_sessions')->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['user_id', 'source_fingerprint']);
            $t->index(['conversation_id', 'sent_at']);
        });
        Schema::create('daily_interaction_stats', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('social_person_id')->constrained()->cascadeOnDelete();
            $t->date('date');
            foreach (['messages_sent', 'messages_received', 'conversations_started_by_user', 'conversations_started_by_person', 'reactions', 'comments', 'mentions', 'tags', 'interaction_count'] as $c) {
                $t->unsignedInteger($c)->default(0);
            }
            $t->unique(['user_id', 'social_person_id', 'date']);
            $t->index(['user_id', 'date']);
        });
        Schema::create('interaction_edges', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('social_person_id')->constrained()->cascadeOnDelete();
            foreach (['messages_sent', 'messages_received', 'sessions_started_by_user', 'sessions_started_by_person', 'reaction_count', 'comment_count', 'reply_count', 'mention_count', 'tag_count', 'active_days'] as $c) {
                $t->unsignedBigInteger($c)->default(0);
            }
            $t->unsignedBigInteger('session_count')->default(0);
            $t->timestamp('first_interaction_at')->nullable();
            $t->timestamp('last_interaction_at')->nullable();
            foreach (['frequency_score', 'active_day_score', 'recency_score', 'mutuality_score', 'initiation_score', 'facebook_score', 'overall_score'] as $c) {
                $t->decimal($c, 5, 2)->default(0);
            }
            $t->string('trend')->default('inactive');
            $t->decimal('trend_percent', 8, 2)->nullable();
            $t->timestamp('computed_at')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'social_person_id']);
            $t->index(['user_id', 'overall_score']);
            $t->index(['user_id', 'last_interaction_at']);
        });
        Schema::create('analytics_exports', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('status')->default('queued');
            $t->string('storage_path')->nullable();
            $t->json('filters')->nullable();
            $t->text('error_message')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('privacy_settings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->boolean('privacy_mode')->default(true);
            $t->boolean('retain_archives')->default(false);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['privacy_settings', 'analytics_exports', 'interaction_edges', 'daily_interaction_stats', 'messages', 'conversation_participants', 'conversations', 'social_people', 'import_sessions'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
