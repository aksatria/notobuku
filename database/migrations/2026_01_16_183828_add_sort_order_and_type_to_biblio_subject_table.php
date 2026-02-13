<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biblio_subject', function (Blueprint $table) {

            // controller mengisi "type" (contoh: topic)
            if (!Schema::hasColumn('biblio_subject', 'type')) {
                $table->string('type', 32)->default('topic')->after('subject_id');
                $table->index('type', 'biblio_subject_type_index');
            }

            // controller mengisi "sort_order" (urut tajuk)
            if (!Schema::hasColumn('biblio_subject', 'sort_order')) {
                $table->unsignedSmallInteger('sort_order')->default(1)->after('type');
                $table->index('sort_order', 'biblio_subject_sort_order_index');
            }
        });

        // Backfill aman untuk data lama
        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('biblio_subject', function (Blueprint $table) {

            if (Schema::hasColumn('biblio_subject', 'sort_order')) {
                $table->dropIndex('biblio_subject_sort_order_index');
                $table->dropColumn('sort_order');
            }

            if (Schema::hasColumn('biblio_subject', 'type')) {
                $table->dropIndex('biblio_subject_type_index');
                $table->dropColumn('type');
            }
        });
    }

    private function backfill(): void
    {
        // isi default jika ada row lama yang null/empty
        if (Schema::hasColumn('biblio_subject', 'type')) {
            DB::statement("UPDATE `biblio_subject` SET `type` = 'topic' WHERE `type` IS NULL OR `type` = ''");
        }
        if (Schema::hasColumn('biblio_subject', 'sort_order')) {
            DB::statement("UPDATE `biblio_subject` SET `sort_order` = 1 WHERE `sort_order` IS NULL OR `sort_order` = 0");
        }
    }
};
