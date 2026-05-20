<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carpetas de notas. Anidables (parent_id self-referencial), estilo Mac
 * Notes. Al borrar una carpeta, sus subcarpetas y notas pasan a la raíz
 * (nullOnDelete) — no se pierde contenido.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('note_folders', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('note_folders')->nullOnDelete();
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_folders');
    }
};
