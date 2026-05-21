<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notas. El cuerpo se guarda como Markdown plano (ver docs/16-notes-plan.md).
 * `folder_id` null = nota en la raíz, sin carpeta.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')->nullable()->constrained('note_folders')->nullOnDelete();
            $table->string('title');
            $table->text('body')->nullable();          // Markdown
            $table->boolean('pinned')->default(false);
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->index('folder_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
