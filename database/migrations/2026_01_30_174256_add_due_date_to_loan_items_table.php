<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_items', function (Blueprint $table) {
            if (!Schema::hasColumn('loan_items', 'due_date')) {
                // Pakai DATE biar ringan & cocok untuk perhitungan overdue harian.
                // Nullable karena data lama mungkin belum punya due_date.
                $table->date('due_date')->nullable()->after('returned_at');

                // Index untuk dashboard:
                // - cari due soon (order by due_date)
                // - filter overdue (due_date < today) dengan returned_at null
                $table->index(['due_date']);
                $table->index(['loan_id', 'returned_at', 'due_date'], 'loan_items_loan_return_due_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('loan_items', function (Blueprint $table) {
            if (Schema::hasColumn('loan_items', 'due_date')) {
                // Hapus index yang dibuat di up()
                try { $table->dropIndex(['due_date']); } catch (\Throwable $e) {}
                try { $table->dropIndex('loan_items_loan_return_due_idx'); } catch (\Throwable $e) {}

                $table->dropColumn('due_date');
            }
        });
    }
};
