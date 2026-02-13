<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interop_metric_points', function (Blueprint $table) {
            $table->id();
            $table->dateTime('minute_at')->unique();
            $table->string('health_label', 20)->default('Sehat');
            $table->string('health_class', 20)->default('good');
            $table->unsignedInteger('p95_ms')->default(0);
            $table->unsignedInteger('invalid_token_total')->default(0);
            $table->unsignedInteger('rate_limited_total')->default(0);
            $table->timestamps();
            $table->index('minute_at');
        });

        Schema::create('interop_metric_daily', function (Blueprint $table) {
            $table->id();
            $table->date('day')->unique();
            $table->unsignedInteger('oai_p95_ms')->default(0);
            $table->unsignedInteger('sru_p95_ms')->default(0);
            $table->unsignedInteger('oai_invalid_token')->default(0);
            $table->unsignedInteger('sru_invalid_token')->default(0);
            $table->unsignedInteger('oai_rate_limited')->default(0);
            $table->unsignedInteger('sru_rate_limited')->default(0);
            $table->unsignedInteger('oai_snapshot_evictions')->default(0);
            $table->dateTime('last_critical_alert_at')->nullable();
            $table->timestamps();
            $table->index('day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interop_metric_daily');
        Schema::dropIfExists('interop_metric_points');
    }
};

