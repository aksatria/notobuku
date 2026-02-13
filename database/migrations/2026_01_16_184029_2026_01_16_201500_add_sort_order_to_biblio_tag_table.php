<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biblio_tag', function (Blueprint $table) {
            if (!Schema::hasColumn('biblio_tag', 'sort_order')) {
                $table->unsignedSmallInteger('sort_order')->default(1)->after('tag_id');
                $table->index('sort_order', 'biblio_tag_sort_order_index');
            }
        });

        // Backfill aman untuk data lama
        if (Schema::hasColumn('biblio_tag', 'sort_order')) {
            DB::statement("UPDATE `biblio_tag` SET `sort_order` = 1 WHERE `sort_order` IS NULL OR `sort_order` = 0");
        }
    }

    public function down(): void
    {
        Schema::table('biblio_tag', function (Blueprint $table) {
            if (Schema::hasColumn('biblio_tag', 'sort_order')) {
                $table->dropIndex('biblio_tag_sort_order_index');
                $table->dropColumn('sort_order');
            }
        });
    }
};
