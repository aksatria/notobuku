<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            if (!Schema::hasColumn('items', 'condition')) {
                // letakkan setelah status agar rapi
                $table->string('condition', 32)->nullable()->after('status'); // baik/sedang/rusak/hilang/dll (bebas)
                $table->index('condition', 'items_condition_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            if (Schema::hasColumn('items', 'condition')) {
                $table->dropIndex('items_condition_index');
                $table->dropColumn('condition');
            }
        });
    }
};
