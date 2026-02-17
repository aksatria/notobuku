<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('circulation_service_calendars')) {
            Schema::create('circulation_service_calendars', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('institution_id')->nullable()->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('name', 120);
                $table->boolean('is_active')->default(true)->index();
                $table->boolean('exclude_weekends')->default(true);
                $table->tinyInteger('priority')->default(0)->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('circulation_service_closures')) {
            Schema::create('circulation_service_closures', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('calendar_id')->index();
                $table->date('closed_on')->index();
                $table->boolean('is_recurring_yearly')->default(false)->index();
                $table->string('label', 160)->nullable();
                $table->timestamps();

                $table->unique(['calendar_id', 'closed_on', 'is_recurring_yearly'], 'circ_service_closure_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('circulation_service_closures');
        Schema::dropIfExists('circulation_service_calendars');
    }
};
