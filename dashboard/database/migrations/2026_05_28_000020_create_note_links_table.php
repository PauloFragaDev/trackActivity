<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Materializa los wikilinks `[[Título]]` que aparecen en el cuerpo de
 * una nota. Una fila por (source_note_id, target_title) — la
 * deduplicación se hace al guardar.
 *
 *   · target_note_id   nullable: enlaces "huérfanos" que apuntan a una
 *                      nota que aún no existe. Cuando esa nota se cree
 *                      con el título matching, el observer rellena el id
 *                      en todas las filas huérfanas.
 *   · target_title     siempre guardado: permite resolver con LIKE/lower
 *                      sin tener que reparsing del body, y sobrevive
 *                      a renombrados (la fila queda apuntando al título
 *                      "viejo" hasta el próximo save de la nota fuente).
 *
 * Backlinks: SELECT WHERE target_note_id = ? agrupado por source.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('note_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_note_id')
                  ->constrained('notes')
                  ->cascadeOnDelete();
            $table->foreignId('target_note_id')
                  ->nullable()
                  ->constrained('notes')
                  ->nullOnDelete();
            $table->string('target_title');
            $table->timestamp('created_at')->nullable();

            $table->index('source_note_id');
            $table->index('target_note_id');
            // Para resolver huérfanos cuando se crea una nota:
            //   WHERE target_note_id IS NULL AND lower(target_title) = ?
            $table->index('target_title');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_links');
    }
};
