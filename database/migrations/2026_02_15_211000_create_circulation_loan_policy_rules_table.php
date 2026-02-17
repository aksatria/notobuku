<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('circulation_loan_policy_rules')) {
            Schema::create('circulation_loan_policy_rules', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('institution_id')->nullable()->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('member_type', 60)->nullable()->index();
                $table->string('collection_type', 60)->nullable()->index();
                $table->unsignedSmallInteger('max_items')->default(3);
                $table->unsignedSmallInteger('default_days')->default(7);
                $table->unsignedSmallInteger('extend_days')->default(7);
                $table->unsignedTinyInteger('max_renewals')->default(2);
                $table->unsignedInteger('fine_rate_per_day')->default(1000);
                $table->unsignedSmallInteger('grace_days')->default(0);
                $table->boolean('can_renew_if_reserved')->default(false);
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedSmallInteger('priority')->default(0)->index();
                $table->string('name', 120)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('circulation_loan_policy_rules');
    }
};
