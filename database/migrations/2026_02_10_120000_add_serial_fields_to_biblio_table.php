<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biblio', function (Blueprint $table) {
            if (!Schema::hasColumn('biblio', 'frequency')) {
                $table->string('frequency')->nullable()->after('is_reference'); // 310
            }
            if (!Schema::hasColumn('biblio', 'former_frequency')) {
                $table->string('former_frequency')->nullable()->after('frequency'); // 321
            }
        });
    }

    public function down(): void
    {
        Schema::table('biblio', function (Blueprint $table) {
            if (Schema::hasColumn('biblio', 'former_frequency')) {
                $table->dropColumn('former_frequency');
            }
            if (Schema::hasColumn('biblio', 'frequency')) {
                $table->dropColumn('frequency');
            }
        });
    }
};
