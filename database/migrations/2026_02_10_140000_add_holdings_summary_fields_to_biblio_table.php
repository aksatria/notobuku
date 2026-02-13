<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biblio', function (Blueprint $table) {
            if (!Schema::hasColumn('biblio', 'holdings_summary')) {
                $table->string('holdings_summary')->nullable()->after('serial_succeeding_issn');
            }
            if (!Schema::hasColumn('biblio', 'holdings_supplement')) {
                $table->string('holdings_supplement')->nullable()->after('holdings_summary');
            }
            if (!Schema::hasColumn('biblio', 'holdings_index')) {
                $table->string('holdings_index')->nullable()->after('holdings_supplement');
            }
        });
    }

    public function down(): void
    {
        Schema::table('biblio', function (Blueprint $table) {
            foreach (['holdings_summary', 'holdings_supplement', 'holdings_index'] as $col) {
                if (Schema::hasColumn('biblio', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
