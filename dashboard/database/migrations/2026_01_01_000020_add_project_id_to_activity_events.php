<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Override manual del proyecto en un activity_event. Cuando se rellena,
 * el Scorer le da un peso enorme — el bloque que contenga este evento
 * acaba atribuido al proyecto elegido por el usuario.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('activity_events', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::table('activity_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });
    }
};
