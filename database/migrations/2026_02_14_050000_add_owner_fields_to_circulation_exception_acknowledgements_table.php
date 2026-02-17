<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('circulation_exception_acknowledgements', function (Blueprint $table) {
            if (!Schema::hasColumn('circulation_exception_acknowledgements', 'owner_user_id')) {
                $table->unsignedBigInteger('owner_user_id')->nullable()->after('member_id')->index();
            }
            if (!Schema::hasColumn('circulation_exception_acknowledgements', 'owner_assigned_at')) {
                $table->timestamp('owner_assigned_at')->nullable()->after('owner_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('circulation_exception_acknowledgements', function (Blueprint $table) {
            if (Schema::hasColumn('circulation_exception_acknowledgements', 'owner_assigned_at')) {
                $table->dropColumn('owner_assigned_at');
            }
            if (Schema::hasColumn('circulation_exception_acknowledgements', 'owner_user_id')) {
                $table->dropColumn('owner_user_id');
            }
        });
    }
};

