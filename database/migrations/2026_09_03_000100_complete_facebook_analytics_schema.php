<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_sessions', function (Blueprint $t) {
            $t->string('owner_resolution_status')->default('unresolved')->index();
            $t->foreignUuid('owner_person_id')->nullable()->constrained('social_people')->nullOnDelete();
            $t->unsignedBigInteger('records_skipped')->default(0);
            $t->json('data_coverage')->nullable();
        });
        Schema::create('friendships', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('social_person_id')->constrained()->cascadeOnDelete();
            $t->string('status')->default('unknown');
            $t->timestamp('friended_at')->nullable();
            $t->foreignUuid('source_import_id')->constrained('import_sessions')->cascadeOnDelete();
            $t->string('fingerprint', 64);
            $t->timestamps();
            $t->unique(['user_id', 'fingerprint']);
            $t->index(['user_id', 'status']);
        });
        Schema::create('facebook_interactions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('source_person_id')->nullable()->constrained('social_people')->nullOnDelete();
            $t->foreignUuid('target_person_id')->nullable()->constrained('social_people')->nullOnDelete();
            $t->string('interaction_type')->index();
            $t->string('object_type');
            $t->string('object_identifier')->nullable();
            $t->timestamp('occurred_at')->nullable()->index();
            $t->decimal('weight', 8, 2)->default(1);
            $t->json('metadata')->nullable();
            $t->foreignUuid('source_import_id')->constrained('import_sessions')->cascadeOnDelete();
            $t->string('fingerprint', 64);
            $t->timestamps();
            $t->unique(['user_id', 'fingerprint']);
            $t->index(['user_id', 'source_person_id', 'occurred_at']);
        });
        Schema::create('parser_diagnostics', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('import_session_id')->constrained()->cascadeOnDelete();
            $t->string('file_path');
            $t->string('parser_name');
            $t->string('detected_schema')->nullable();
            $t->unsignedTinyInteger('confidence')->default(0);
            $t->unsignedBigInteger('records_seen')->default(0);
            $t->unsignedBigInteger('records_imported')->default(0);
            $t->unsignedBigInteger('records_skipped')->default(0);
            $t->json('warnings')->nullable();
            $t->boolean('completed')->default(false);
            $t->timestamps();
            $t->unique(['import_session_id', 'file_path', 'parser_name'], 'parser_diag_unique');
        });
        Schema::create('owner_identity_candidates', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('import_session_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('social_person_id')->constrained()->cascadeOnDelete();
            $t->string('signal');
            $t->unsignedTinyInteger('confidence')->default(0);
            $t->timestamps();
            $t->unique(['import_session_id', 'social_person_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_identity_candidates');
        Schema::dropIfExists('parser_diagnostics');
        Schema::dropIfExists('facebook_interactions');
        Schema::dropIfExists('friendships');
        Schema::table('import_sessions', function (Blueprint $t) {
            $t->dropConstrainedForeignId('owner_person_id');
            $t->dropColumn(['owner_resolution_status', 'records_skipped', 'data_coverage']);
        });
    }
};
