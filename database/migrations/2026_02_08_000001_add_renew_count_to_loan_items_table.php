<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_items', function (Blueprint $table) {
            if (!Schema::hasColumn('loan_items', 'renew_count')) {
                $table->unsignedTinyInteger('renew_count')
                    ->default(0)
                    ->after('due_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('loan_items', function (Blueprint $table) {
            if (Schema::hasColumn('loan_items', 'renew_count')) {
                $table->dropColumn('renew_count');
            }
        });
    }
};
