<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('reservation_policy_rules')) {
            Schema::create('reservation_policy_rules', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('institution_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('member_type', 30)->nullable()->index();
                $table->string('collection_type', 40)->nullable()->index();
                $table->unsignedInteger('max_active_reservations')->default(5);
                $table->unsignedInteger('max_queue_per_biblio')->default(30);
                $table->unsignedInteger('hold_hours')->default(48);
                $table->integer('priority_weight')->default(0);
                $table->boolean('is_enabled')->default(true)->index();
                $table->string('label', 120)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['institution_id', 'is_enabled', 'branch_id'], 'rpr_inst_enabled_branch_idx');
                $table->index(['institution_id', 'member_type', 'collection_type'], 'rpr_inst_member_collection_idx');
            });
        }

        if (Schema::hasTable('reservations')) {
            Schema::table('reservations', function (Blueprint $table) {
                if (!Schema::hasColumn('reservations', 'priority_score')) {
                    $table->integer('priority_score')->default(0)->after('queue_no')->index();
                }
                if (!Schema::hasColumn('reservations', 'policy_rule_id')) {
                    $table->unsignedBigInteger('policy_rule_id')->nullable()->after('priority_score')->index();
                }
                if (!Schema::hasColumn('reservations', 'policy_snapshot')) {
                    $table->longText('policy_snapshot')->nullable()->after('policy_rule_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('reservations')) {
            Schema::table('reservations', function (Blueprint $table) {
                if (Schema::hasColumn('reservations', 'policy_snapshot')) {
                    $table->dropColumn('policy_snapshot');
                }
                if (Schema::hasColumn('reservations', 'policy_rule_id')) {
                    $table->dropColumn('policy_rule_id');
                }
                if (Schema::hasColumn('reservations', 'priority_score')) {
                    $table->dropColumn('priority_score');
                }
            });
        }

        Schema::dropIfExists('reservation_policy_rules');
    }
};
