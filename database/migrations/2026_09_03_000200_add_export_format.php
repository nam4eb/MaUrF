<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analytics_exports', function (Blueprint $t) {
            $t->string('format')->default('xlsx');
            $t->timestamp('expires_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('analytics_exports', function (Blueprint $t) {
            $t->dropColumn(['format', 'expires_at']);
        });
    }
};
