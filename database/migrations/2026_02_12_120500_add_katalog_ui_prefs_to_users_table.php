<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'katalog_skeleton_enabled')) {
                $table->boolean('katalog_skeleton_enabled')->default(false)->after('sidebar_collapsed');
            }
            if (!Schema::hasColumn('users', 'katalog_preload_margin')) {
                $table->unsignedSmallInteger('katalog_preload_margin')->default(300)->after('katalog_skeleton_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'katalog_preload_margin')) {
                $table->dropColumn('katalog_preload_margin');
            }
            if (Schema::hasColumn('users', 'katalog_skeleton_enabled')) {
                $table->dropColumn('katalog_skeleton_enabled');
            }
        });
    }
};
