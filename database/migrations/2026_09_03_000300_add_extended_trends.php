<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interaction_edges', function (Blueprint $t) {
            $t->string('trend_90')->default('inactive');
            $t->decimal('trend_90_percent', 8, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('interaction_edges', function (Blueprint $t) {
            $t->dropColumn(['trend_90', 'trend_90_percent']);
        });
    }
};
