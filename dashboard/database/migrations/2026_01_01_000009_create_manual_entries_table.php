<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entradas manuales: tramos de tiempo que el usuario añade a mano
 * (reuniones, correcciones de horas) y que NO produce el tracker.
 *
 * Conviven con time_blocks pero son una capa independiente: el Aggregator
 * nunca las toca y pueden tener inicio/fin arbitrarios (fuera del grid).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('manual_entries', function (Blueprint $table) {
            $table->id();
            $table->dateTime('starts_at');                  // UTC
            $table->dateTime('ends_at');                    // UTC
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('kind')->default('meeting');     // meeting|focus|other
            $table->string('title');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('starts_at');
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_entries');
    }
};
