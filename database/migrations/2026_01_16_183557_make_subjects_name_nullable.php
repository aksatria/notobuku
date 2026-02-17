<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildSubjectsNameNullable();
            return;
        }

        // Buat kolom "name" boleh NULL agar controller yang insert (term, scheme, normalized_term)
        // tidak error lagi pada MySQL strict mode.
        DB::statement("ALTER TABLE `subjects` MODIFY `name` VARCHAR(255) NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildSubjectsNameNotNull();
            return;
        }

        // Kembalikan ke NOT NULL (hati-hati kalau sudah ada row yang name-nya NULL)
        // Kita set NULL -> '' biar aman sebelum balik ke NOT NULL.
        DB::statement("UPDATE `subjects` SET `name` = COALESCE(`name`, '') WHERE `name` IS NULL");
        DB::statement("ALTER TABLE `subjects` MODIFY `name` VARCHAR(255) NOT NULL");
    }

    private function rebuildSubjectsNameNullable(): void
    {
        if (!Schema::hasTable('subjects')) {
            return;
        }

        Schema::create('subjects_tmp', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            if (Schema::hasColumn('subjects', 'code')) {
                $table->string('code', 50)->nullable();
            }
            if (Schema::hasColumn('subjects', 'normalized_term')) {
                $table->string('normalized_term')->nullable();
            }
            if (Schema::hasColumn('subjects', 'term')) {
                $table->string('term')->nullable();
            }
            if (Schema::hasColumn('subjects', 'scheme')) {
                $table->string('scheme', 32)->nullable();
            }
            $table->timestamps();
        });

        $columns = ['id', 'name'];
        foreach (['code', 'normalized_term', 'term', 'scheme', 'created_at', 'updated_at'] as $col) {
            if (Schema::hasColumn('subjects', $col)) {
                $columns[] = $col;
            }
        }

        DB::table('subjects_tmp')->insertUsing($columns, DB::table('subjects')->select($columns));
        Schema::drop('subjects');
        Schema::rename('subjects_tmp', 'subjects');

        Schema::table('subjects', function (Blueprint $table) {
            $table->index('name');
            if (Schema::hasColumn('subjects', 'code')) {
                $table->unique(['code'], 'subjects_code_unique');
            }
            if (Schema::hasColumn('subjects', 'normalized_term')) {
                $table->index('normalized_term', 'subjects_normalized_term_index');
            }
            if (Schema::hasColumn('subjects', 'term')) {
                $table->index('term', 'subjects_term_index');
            }
            if (Schema::hasColumn('subjects', 'scheme')) {
                $table->index('scheme', 'subjects_scheme_index');
            }
        });
    }

    private function rebuildSubjectsNameNotNull(): void
    {
        DB::table('subjects')->whereNull('name')->update(['name' => '']);

        if (!Schema::hasTable('subjects')) {
            return;
        }

        Schema::create('subjects_tmp', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            if (Schema::hasColumn('subjects', 'code')) {
                $table->string('code', 50)->nullable();
            }
            if (Schema::hasColumn('subjects', 'normalized_term')) {
                $table->string('normalized_term')->nullable();
            }
            if (Schema::hasColumn('subjects', 'term')) {
                $table->string('term')->nullable();
            }
            if (Schema::hasColumn('subjects', 'scheme')) {
                $table->string('scheme', 32)->nullable();
            }
            $table->timestamps();
        });

        $columns = ['id', 'name'];
        foreach (['code', 'normalized_term', 'term', 'scheme', 'created_at', 'updated_at'] as $col) {
            if (Schema::hasColumn('subjects', $col)) {
                $columns[] = $col;
            }
        }

        DB::table('subjects_tmp')->insertUsing($columns, DB::table('subjects')->select($columns));
        Schema::drop('subjects');
        Schema::rename('subjects_tmp', 'subjects');

        Schema::table('subjects', function (Blueprint $table) {
            $table->index('name');
            if (Schema::hasColumn('subjects', 'code')) {
                $table->unique(['code'], 'subjects_code_unique');
            }
            if (Schema::hasColumn('subjects', 'normalized_term')) {
                $table->index('normalized_term', 'subjects_normalized_term_index');
            }
            if (Schema::hasColumn('subjects', 'term')) {
                $table->index('term', 'subjects_term_index');
            }
            if (Schema::hasColumn('subjects', 'scheme')) {
                $table->index('scheme', 'subjects_scheme_index');
            }
        });
    }
};
