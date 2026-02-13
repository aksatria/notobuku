<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acquisitions_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('purchase_order_id')->nullable()->after('book_request_id');
            $table->unsignedBigInteger('purchase_order_line_id')->nullable()->after('purchase_order_id');

            $table->index(['purchase_order_id'], 'acq_req_po_id_idx');
            $table->index(['purchase_order_line_id'], 'acq_req_po_line_id_idx');

            $table->foreign('purchase_order_id', 'acq_req_po_id_fk')
                ->references('id')->on('purchase_orders')
                ->onDelete('set null');
            $table->foreign('purchase_order_line_id', 'acq_req_po_line_id_fk')
                ->references('id')->on('purchase_order_lines')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('acquisitions_requests', function (Blueprint $table) {
            $table->dropForeign('acq_req_po_id_fk');
            $table->dropForeign('acq_req_po_line_id_fk');
            $table->dropIndex('acq_req_po_id_idx');
            $table->dropIndex('acq_req_po_line_id_idx');
            $table->dropColumn(['purchase_order_id', 'purchase_order_line_id']);
        });
    }
};
