<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('manual_entries', function (Blueprint $table) {
            // Mood al cerrar el pomodoro (1..5), opcional.
            $table->unsignedTinyInteger('mood')->nullable()->after('notes');
            // ¿Avanzaste? yes|partial|no, opcional.
            $table->string('progress', 16)->nullable()->after('mood');
            // % de foco real durante la entry (0..1) — cruzado contra TimeBlock dominante.
            $table->float('focused_ratio')->nullable()->after('progress');
        });
    }

    public function down(): void
    {
        Schema::table('manual_entries', function (Blueprint $table) {
            $table->dropColumn(['mood', 'progress', 'focused_ratio']);
        });
    }
};
