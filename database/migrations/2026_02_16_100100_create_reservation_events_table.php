<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('reservation_events')) {
            Schema::create('reservation_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('institution_id')->index();
                $table->unsignedBigInteger('reservation_id')->nullable()->index();
                $table->unsignedBigInteger('member_id')->nullable()->index();
                $table->unsignedBigInteger('biblio_id')->nullable()->index();
                $table->unsignedBigInteger('item_id')->nullable()->index();
                $table->unsignedBigInteger('actor_user_id')->nullable()->index();
                $table->string('event_type', 40)->index();
                $table->string('status_from', 20)->nullable()->index();
                $table->string('status_to', 20)->nullable()->index();
                $table->unsignedInteger('queue_no')->nullable();
                $table->integer('wait_minutes')->nullable();
                $table->longText('meta')->nullable();
                $table->timestamps();

                $table->index(['institution_id', 'event_type', 'created_at'], 're_inst_type_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_events');
    }
};
