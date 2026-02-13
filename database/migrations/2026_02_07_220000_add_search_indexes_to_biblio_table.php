<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biblio', function (Blueprint $table) {
            $table->index('title', 'biblio_title_idx');
            $table->index('normalized_title', 'biblio_normalized_title_idx');
            $table->index('publisher', 'biblio_publisher_idx');
            $table->index('isbn', 'biblio_isbn_idx');
            $table->index('ddc', 'biblio_ddc_idx');
            $table->index('call_number', 'biblio_call_number_idx');
        });
    }

    public function down(): void
    {
        Schema::table('biblio', function (Blueprint $table) {
            $table->dropIndex('biblio_title_idx');
            $table->dropIndex('biblio_normalized_title_idx');
            $table->dropIndex('biblio_publisher_idx');
            $table->dropIndex('biblio_isbn_idx');
            $table->dropIndex('biblio_ddc_idx');
            $table->dropIndex('biblio_call_number_idx');
        });
    }
};
