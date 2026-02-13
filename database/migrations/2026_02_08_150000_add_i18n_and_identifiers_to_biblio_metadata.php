<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biblio_metadata', function (Blueprint $table) {
            if (!Schema::hasColumn('biblio_metadata', 'dublin_core_i18n_json')) {
                $table->json('dublin_core_i18n_json')->nullable()->after('dublin_core_json');
            }
            if (!Schema::hasColumn('biblio_metadata', 'global_identifiers_json')) {
                $table->json('global_identifiers_json')->nullable()->after('marc_core_json');
            }
        });
    }

    public function down(): void
    {
        Schema::table('biblio_metadata', function (Blueprint $table) {
            if (Schema::hasColumn('biblio_metadata', 'dublin_core_i18n_json')) {
                $table->dropColumn('dublin_core_i18n_json');
            }
            if (Schema::hasColumn('biblio_metadata', 'global_identifiers_json')) {
                $table->dropColumn('global_identifiers_json');
            }
        });
    }
};
