<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subtareas (checkboxes) dentro de una tarea del Kanban. Mismo modelo que
 * en code-kanban: lista ordenada de items con texto + estado de marcado.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('task_checkboxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('title');
            $table->boolean('checked')->default(false);
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->index(['task_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_checkboxes');
    }
};
