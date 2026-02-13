<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('biblio', function (Blueprint $table) {
            $table->string('cover_path', 255)->nullable()->after('call_number');
        });
    }

    public function down(): void
    {
        Schema::table('biblio', function (Blueprint $table) {
            $table->dropColumn('cover_path');
        });
    }
};
