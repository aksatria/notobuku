<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('circulation_exception_acknowledgements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institution_id')->default(0)->index();
            $table->date('snapshot_date')->index();
            $table->string('fingerprint', 40);
            $table->string('exception_type', 80)->nullable()->index();
            $table->string('severity', 20)->nullable()->index();
            $table->unsignedBigInteger('loan_id')->nullable()->index();
            $table->unsignedBigInteger('loan_item_id')->nullable()->index();
            $table->unsignedBigInteger('item_id')->nullable()->index();
            $table->string('barcode', 120)->nullable()->index();
            $table->unsignedBigInteger('member_id')->nullable()->index();
            $table->string('status', 20)->default('open')->index(); // open|ack|resolved
            $table->text('ack_note')->nullable();
            $table->unsignedBigInteger('ack_by')->nullable()->index();
            $table->timestamp('ack_at')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable()->index();
            $table->timestamp('resolved_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['institution_id', 'snapshot_date', 'fingerprint'], 'circ_exc_ack_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circulation_exception_acknowledgements');
    }
};

