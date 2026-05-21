<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vínculo opcional de una entrada manual con una tarea del Kanban: permite
 * que la tarea acumule el tiempo de las entradas asignadas.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('manual_entries', function (Blueprint $table) {
            $table->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('manual_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('task_id');
        });
    }
};
