<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Limpia la mezcla de "Timer de tarea + Pomodoro" del esquema. El nuevo
 * pomodoro es 100% client-side, sin tabla ni acoplamiento a tareas.
 *
 *   · active_timers: tabla entera fuera. La fuente de verdad pasa a ser
 *     localStorage en el navegador (consistente con que el pomodoro ya no
 *     está ligado a tareas ni proyectos).
 *
 *   · manual_entries.mood / progress / focused_ratio: columnas del cierre
 *     post-foco antiguo. Como el pomodoro pierde el modal de cierre, las
 *     entradas manuales ya no necesitan ese metadato.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('active_timers');

        if (Schema::hasTable('manual_entries')) {
            Schema::table('manual_entries', function (Blueprint $table) {
                foreach (['mood', 'progress', 'focused_ratio'] as $col) {
                    if (Schema::hasColumn('manual_entries', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        // No reconstruimos el esquema viejo — el rollback rehace tabla y
        // columnas vacías solo para que la migración sea reversible.
        if (! Schema::hasTable('active_timers')) {
            Schema::create('active_timers', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
            });
        }
        Schema::table('manual_entries', function (Blueprint $table) {
            $table->unsignedTinyInteger('mood')->nullable();
            $table->string('progress', 16)->nullable();
            $table->float('focused_ratio')->nullable();
        });
    }
};
