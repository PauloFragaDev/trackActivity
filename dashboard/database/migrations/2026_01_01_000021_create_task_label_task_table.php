<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivote tarea ↔ etiqueta. Cascade en ambos lados: si se borra la tarea o
 * la etiqueta, las asignaciones desaparecen.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('task_label_task', function (Blueprint $table) {
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('label_id')->constrained('task_labels')->cascadeOnDelete();
            $table->primary(['task_id', 'label_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_label_task');
    }
};
