<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biblio', function (Blueprint $table) {
            if (!Schema::hasColumn('biblio', 'serial_beginning')) {
                $table->string('serial_beginning')->nullable()->after('former_frequency');
            }
            if (!Schema::hasColumn('biblio', 'serial_ending')) {
                $table->string('serial_ending')->nullable()->after('serial_beginning');
            }
            if (!Schema::hasColumn('biblio', 'serial_first_issue')) {
                $table->string('serial_first_issue')->nullable()->after('serial_ending');
            }
            if (!Schema::hasColumn('biblio', 'serial_last_issue')) {
                $table->string('serial_last_issue')->nullable()->after('serial_first_issue');
            }
            if (!Schema::hasColumn('biblio', 'serial_source_note')) {
                $table->string('serial_source_note')->nullable()->after('serial_last_issue');
            }
            if (!Schema::hasColumn('biblio', 'serial_preceding_title')) {
                $table->string('serial_preceding_title')->nullable()->after('serial_source_note');
            }
            if (!Schema::hasColumn('biblio', 'serial_preceding_issn')) {
                $table->string('serial_preceding_issn')->nullable()->after('serial_preceding_title');
            }
            if (!Schema::hasColumn('biblio', 'serial_succeeding_title')) {
                $table->string('serial_succeeding_title')->nullable()->after('serial_preceding_issn');
            }
            if (!Schema::hasColumn('biblio', 'serial_succeeding_issn')) {
                $table->string('serial_succeeding_issn')->nullable()->after('serial_succeeding_title');
            }
        });
    }

    public function down(): void
    {
        Schema::table('biblio', function (Blueprint $table) {
            foreach ([
                'serial_beginning',
                'serial_ending',
                'serial_first_issue',
                'serial_last_issue',
                'serial_source_note',
                'serial_preceding_title',
                'serial_preceding_issn',
                'serial_succeeding_title',
                'serial_succeeding_issn',
            ] as $col) {
                if (Schema::hasColumn('biblio', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
