<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('search_queries', function (Blueprint $table) {
            $table->string('auto_suggestion_query', 180)->nullable()->after('zero_resolution_link');
            $table->decimal('auto_suggestion_score', 5, 2)->nullable()->after('auto_suggestion_query');
            $table->string('auto_suggestion_status', 24)->default('open')->after('auto_suggestion_score');

            $table->index(['institution_id', 'last_hits', 'auto_suggestion_status'], 'search_queries_auto_suggestion_idx');
        });
    }

    public function down(): void
    {
        Schema::table('search_queries', function (Blueprint $table) {
            $table->dropIndex('search_queries_auto_suggestion_idx');
            $table->dropColumn([
                'auto_suggestion_query',
                'auto_suggestion_score',
                'auto_suggestion_status',
            ]);
        });
    }
};

