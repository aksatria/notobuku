<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biblio_metrics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institution_id');
            $table->unsignedBigInteger('biblio_id');
            $table->unsignedInteger('click_count')->default(0);
            $table->unsignedInteger('borrow_count')->default(0);
            $table->timestamp('last_clicked_at')->nullable();
            $table->timestamp('last_borrowed_at')->nullable();
            $table->timestamps();

            $table->unique(['institution_id', 'biblio_id']);
            $table->index(['institution_id', 'biblio_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biblio_metrics');
    }
};
