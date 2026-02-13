<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'katalog_preload_set')) {
                $table->boolean('katalog_preload_set')->default(false)->after('katalog_preload_margin');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'katalog_preload_set')) {
                $table->dropColumn('katalog_preload_set');
            }
        });
    }
};
