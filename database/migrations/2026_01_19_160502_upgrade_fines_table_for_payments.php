<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('fines')) {
            return;
        }

        Schema::table('fines', function (Blueprint $table) {
            // status: unpaid|paid|void
            if (!Schema::hasColumn('fines', 'status')) {
                $table->string('status', 20)->default('unpaid')->after('loan_item_id');
            }

            if (!Schema::hasColumn('fines', 'days_late')) {
                $table->integer('days_late')->default(0)->after('status');
            }

            if (!Schema::hasColumn('fines', 'rate')) {
                $table->integer('rate')->default(1000)->after('days_late');
            }

            if (!Schema::hasColumn('fines', 'amount')) {
                $table->integer('amount')->default(0)->after('rate');
            }

            // ✅ ini yang bikin error tadi kalau belum ada
            if (!Schema::hasColumn('fines', 'paid_amount')) {
                $table->integer('paid_amount')->nullable()->after('amount');
            }

            if (!Schema::hasColumn('fines', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('paid_amount');
            }

            if (!Schema::hasColumn('fines', 'paid_by')) {
                $table->unsignedBigInteger('paid_by')->nullable()->after('paid_at');
            }

            if (!Schema::hasColumn('fines', 'notes')) {
                $table->text('notes')->nullable()->after('paid_by');
            }

            if (!Schema::hasColumn('fines', 'updated_at')) {
                $table->timestamps();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('fines')) return;

        Schema::table('fines', function (Blueprint $table) {
            foreach (['notes','paid_by','paid_at','paid_amount','amount','rate','days_late','status'] as $col) {
                if (Schema::hasColumn('fines', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
