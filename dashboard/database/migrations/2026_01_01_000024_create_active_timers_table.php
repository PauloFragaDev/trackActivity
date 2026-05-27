<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cronómetro activo. Solo se mantiene 1 fila a la vez — el controller
 * garantiza la unicidad. Al parar se borra y se crea una manual_entry
 * con el tiempo invertido.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('active_timers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->dateTime('starts_at');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('active_timers');
    }
};
