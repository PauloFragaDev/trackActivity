<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vínculo de una tarea con su item en un GitHub Project, para la
 * sincronización del tablero (ver docs/17-github-projects-sync.md).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('github_item_id')->nullable()->unique();
            $table->dateTime('github_synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['github_item_id', 'github_synced_at']);
        });
    }
};
