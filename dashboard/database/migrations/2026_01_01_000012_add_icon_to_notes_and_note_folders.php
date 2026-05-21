<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Icono (emoji) opcional para notas y carpetas — primer paso de "Notas v2"
 * hacia una experiencia más tipo Notion.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->string('icon')->nullable();
        });
        Schema::table('note_folders', function (Blueprint $table) {
            $table->string('icon')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropColumn('icon');
        });
        Schema::table('note_folders', function (Blueprint $table) {
            $table->dropColumn('icon');
        });
    }
};
