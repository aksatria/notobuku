<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kalau tabel belum ada (kasus edge), bikin sekalian.
        if (!Schema::hasTable('shelves')) {
            Schema::create('shelves', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('institution_id')->index();
                $table->unsignedBigInteger('branch_id')->index();
                $table->string('name', 150);
                $table->string('code', 50)->nullable();
                $table->string('location', 255)->nullable();
                $table->text('notes')->nullable();
                $table->unsignedInteger('sort_order')->default(0)->index();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();

                $table->unique(['institution_id', 'branch_id', 'name'], 'shelves_inst_branch_name_unique');
                $table->index(['institution_id', 'branch_id', 'is_active'], 'shelves_inst_branch_active_idx');

                $table->foreign('branch_id')
                    ->references('id')->on('branches')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();
            });

            return;
        }

        // ALTER TABLE (aman)
        Schema::table('shelves', function (Blueprint $table) {
            if (!Schema::hasColumn('shelves', 'institution_id')) {
                $table->unsignedBigInteger('institution_id')->default(1)->index()->after('id');
            }

            if (!Schema::hasColumn('shelves', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->index()->after('institution_id');
            }

            if (!Schema::hasColumn('shelves', 'name')) {
                $table->string('name', 150)->after('branch_id');
            } else {
                // pastikan panjang cukup (opsional, aman di MySQL kalau sudah sama/lebih kecil bisa error)
                // jadi kita tidak paksa modify di sini.
            }

            if (!Schema::hasColumn('shelves', 'code')) {
                $table->string('code', 50)->nullable()->after('name');
            }

            if (!Schema::hasColumn('shelves', 'location')) {
                $table->string('location', 255)->nullable()->after('code');
            }

            if (!Schema::hasColumn('shelves', 'notes')) {
                $table->text('notes')->nullable()->after('location');
            }

            if (!Schema::hasColumn('shelves', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->index()->after('notes');
            }

            if (!Schema::hasColumn('shelves', 'is_active')) {
                $table->boolean('is_active')->default(true)->index()->after('sort_order');
            }
        });

        // Index gabungan (cek dulu biar tidak duplicate)
        $this->ensureIndexExists('shelves', 'shelves_inst_branch_active_idx', ['institution_id', 'branch_id', 'is_active']);

        // Unique constraint (cek dulu)
        $this->ensureUniqueExists('shelves', 'shelves_inst_branch_name_unique', ['institution_id', 'branch_id', 'name']);

        // Foreign key branch_id (opsional: hanya jika branches ada)
        if (Schema::hasTable('branches')) {
            $this->ensureForeignKeyExists('shelves', 'shelves_branch_id_foreign', function () {
                Schema::table('shelves', function (Blueprint $table) {
                    $table->foreign('branch_id')
                        ->references('id')->on('branches')
                        ->cascadeOnUpdate()
                        ->restrictOnDelete();
                });
            });
        }
    }

    public function down(): void
    {
        // Rollback aman: hapus yang kita tambah (kalau ada)
        if (!Schema::hasTable('shelves')) return;

        // drop FK / indexes / unique kalau ada
        $this->dropConstraintIfExists('shelves', 'shelves_branch_id_foreign', 'foreign');
        $this->dropConstraintIfExists('shelves', 'shelves_inst_branch_active_idx', 'index');
        $this->dropConstraintIfExists('shelves', 'shelves_inst_branch_name_unique', 'unique');

        Schema::table('shelves', function (Blueprint $table) {
            if (Schema::hasColumn('shelves', 'sort_order')) $table->dropColumn('sort_order');
            if (Schema::hasColumn('shelves', 'is_active')) $table->dropColumn('is_active');
            if (Schema::hasColumn('shelves', 'code')) $table->dropColumn('code');
            if (Schema::hasColumn('shelves', 'location')) $table->dropColumn('location');
            if (Schema::hasColumn('shelves', 'notes')) $table->dropColumn('notes');
            // institution_id/branch_id/name biasanya sudah existed—kita tidak drop agar tidak merusak sistem lama.
        });
    }

    private function ensureIndexExists(string $table, string $indexName, array $columns): void
    {
        $exists = DB::selectOne("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
        if ($exists) return;

        Schema::table($table, function (Blueprint $t) use ($columns, $indexName) {
            $t->index($columns, $indexName);
        });
    }

    private function ensureUniqueExists(string $table, string $indexName, array $columns): void
    {
        $exists = DB::selectOne("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
        if ($exists) return;

        Schema::table($table, function (Blueprint $t) use ($columns, $indexName) {
            $t->unique($columns, $indexName);
        });
    }

    private function ensureForeignKeyExists(string $table, string $fkName, callable $createFk): void
    {
        $row = DB::selectOne("
            SELECT CONSTRAINT_NAME
            FROM information_schema.REFERENTIAL_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND CONSTRAINT_NAME = ?
            LIMIT 1
        ", [$table, $fkName]);

        if ($row) return;

        $createFk();
    }

    private function dropConstraintIfExists(string $table, string $name, string $type): void
    {
        try {
            Schema::table($table, function (Blueprint $t) use ($name, $type) {
                if ($type === 'foreign') $t->dropForeign($name);
                if ($type === 'unique')  $t->dropUnique($name);
                if ($type === 'index')   $t->dropIndex($name);
            });
        } catch (\Throwable $e) {
            // sengaja diabaikan supaya rollback tidak gagal kalau constraint tidak ada
        }
    }
};
