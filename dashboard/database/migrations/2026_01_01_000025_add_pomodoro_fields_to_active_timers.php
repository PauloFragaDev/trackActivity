<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('active_timers', function (Blueprint $table) {
            // Estado de la fase actual del pomodoro: focus|short_break|long_break.
            // La pausa es un flag aparte (paused_at) para no perder el estado al reanudar.
            $table->string('state', 16)->default('focus')->after('task_id');
            // Cuántos focus completados en esta sesión (para decidir long_break cada N).
            $table->unsignedSmallInteger('cycle_count')->default(0)->after('state');
            // Cuándo empezó la fase actual (puede no coincidir con created_at en break/swap).
            $table->dateTime('phase_started_at')->nullable()->after('starts_at');
            // Si está en pausa, cuándo se pausó.
            $table->dateTime('paused_at')->nullable()->after('phase_started_at');
            // Segundos acumulados de pausa dentro de la fase actual (para que el contador no salte).
            $table->unsignedInteger('paused_offset_seconds')->default(0)->after('paused_at');
        });
    }

    public function down(): void
    {
        Schema::table('active_timers', function (Blueprint $table) {
            $table->dropColumn([
                'state',
                'cycle_count',
                'phase_started_at',
                'paused_at',
                'paused_offset_seconds',
            ]);
        });
    }
};
