<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biblio_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institution_id');
            $table->unsignedBigInteger('biblio_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('event_type', 20); // click | borrow
            $table->timestamp('created_at')->useCurrent();

            $table->index(['institution_id', 'event_type', 'created_at'], 'biblio_events_inst_event_created_idx');
            $table->index(['institution_id', 'branch_id', 'event_type', 'created_at'], 'biblio_events_inst_branch_event_created_idx');
            $table->index(['biblio_id'], 'biblio_events_biblio_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biblio_events');
    }
};
