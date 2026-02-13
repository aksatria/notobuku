<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {

            /**
             * Catatan:
             * - Tabel items kamu tidak punya kolom "location".
             * - Jadi jangan pakai after('location').
             * - Kita tempatkan field baru setelah kolom yang "umumnya ada" di items: barcode/status/condition.
             */

            // Lokasi detail (karena kamu sudah punya branches/shelves)
            if (!Schema::hasColumn('items', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('biblio_id');
                $table->index('branch_id', 'items_branch_id_index');
            }

            if (!Schema::hasColumn('items', 'shelf_id')) {
                $table->unsignedBigInteger('shelf_id')->nullable()->after('branch_id');
                $table->index('shelf_id', 'items_shelf_id_index');
            }

            // Keterangan lokasi tambahan (letakkan setelah status agar aman)
            if (!Schema::hasColumn('items', 'location_note')) {
                if (Schema::hasColumn('items', 'status')) {
                    $table->string('location_note')->nullable()->after('status');
                } else if (Schema::hasColumn('items', 'barcode')) {
                    $table->string('location_note')->nullable()->after('barcode');
                } else {
                    $table->string('location_note')->nullable();
                }
            }

            // Perolehan
            if (!Schema::hasColumn('items', 'acquired_at')) {
                if (Schema::hasColumn('items', 'condition')) {
                    $table->date('acquired_at')->nullable()->after('condition');
                } else if (Schema::hasColumn('items', 'status')) {
                    $table->date('acquired_at')->nullable()->after('status');
                } else {
                    $table->date('acquired_at')->nullable();
                }
                $table->index('acquired_at', 'items_acquired_at_index');
            }

            if (!Schema::hasColumn('items', 'acquisition_source')) {
                $table->string('acquisition_source', 32)->default('beli')->after('acquired_at'); // beli/hibah/tukar
                $table->index('acquisition_source', 'items_acq_source_index');
            }

            if (!Schema::hasColumn('items', 'price')) {
                $table->unsignedInteger('price')->nullable()->after('acquisition_source'); // Rp (integer)
            }

            // Identitas inventaris tambahan
            if (!Schema::hasColumn('items', 'inventory_number')) {
                $table->string('inventory_number', 64)->nullable()->after('barcode'); // no induk inventaris (opsional)
                $table->unique('inventory_number', 'items_inventory_number_unique');
            }

            // Status sirkulasi tambahan (opsional)
            if (!Schema::hasColumn('items', 'circulation_status')) {
                if (Schema::hasColumn('items', 'status')) {
                    $table->string('circulation_status', 32)->default('circulating')->after('status');
                } else {
                    $table->string('circulation_status', 32)->default('circulating');
                }
                $table->index('circulation_status', 'items_circulation_status_index');
            }

            if (!Schema::hasColumn('items', 'is_reference')) {
                $table->boolean('is_reference')->default(false)->after('circulation_status');
                $table->index('is_reference', 'items_is_reference_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {

            $cols = [
                'branch_id',
                'shelf_id',
                'location_note',
                'acquired_at',
                'acquisition_source',
                'price',
                'inventory_number',
                'circulation_status',
                'is_reference',
            ];

            foreach ($cols as $col) {
                if (!Schema::hasColumn('items', $col)) continue;

                $idx = match ($col) {
                    'branch_id' => 'items_branch_id_index',
                    'shelf_id' => 'items_shelf_id_index',
                    'acquired_at' => 'items_acquired_at_index',
                    'acquisition_source' => 'items_acq_source_index',
                    'inventory_number' => 'items_inventory_number_unique',
                    'circulation_status' => 'items_circulation_status_index',
                    'is_reference' => 'items_is_reference_index',
                    default => null,
                };

                if ($idx) {
                    $table->dropIndex($idx);
                }

                $table->dropColumn($col);
            }
        });
    }
};
