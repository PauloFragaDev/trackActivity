<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paleta global de etiquetas para las tareas. Las etiquetas se definen una
 * vez (título + color) y se aplican a N tareas mediante el pivote
 * task_label_task. Inspirado en el modelo de code-kanban (settings.labels).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('task_labels', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('color', 16);      // hex con # (ej. "#3B82F6")
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->index('position');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_labels');
    }
};
