<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('biblio_attachments')) {
            return;
        }

        Schema::create('biblio_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('biblio_id');
            $table->string('title', 255);
            $table->string('file_path', 255);
            $table->string('file_name', 255)->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('visibility', 20)->default('staff'); // public | member | staff
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['biblio_id', 'visibility']);
            $table->foreign('biblio_id')->references('id')->on('biblio')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biblio_attachments');
    }
};
