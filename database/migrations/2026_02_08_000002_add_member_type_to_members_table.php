<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('members')) {
            return;
        }

        Schema::table('members', function (Blueprint $table) {
            if (!Schema::hasColumn('members', 'member_type')) {
                $table->string('member_type', 30)->default('member')->after('member_code');
                $table->index('member_type');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('members')) {
            return;
        }

        Schema::table('members', function (Blueprint $table) {
            if (Schema::hasColumn('members', 'member_type')) {
                $table->dropIndex(['member_type']);
                $table->dropColumn('member_type');
            }
        });
    }
};
