<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Identificador de la card en code-kanban (UUID que genera la
            // extensión). Es el ancla del upsert bidireccional. Nullable
            // porque las tareas pre-existentes no tienen contrapartida y
            // se enlazan la primera vez que aparezcan en una sync.
            $table->string('kanban_card_id')->nullable()->after('github_dirty');
            $table->index('kanban_card_id');

            // Cuándo ocurrió la última sync con code-kanban (server-side).
            // Sirve para detectar "el archivo del cliente vino con un
            // timestamp anterior a esto" → ignorar (conflicto, gana server).
            $table->timestamp('kanban_synced_at')->nullable()->after('kanban_card_id');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['kanban_card_id']);
            $table->dropColumn(['kanban_card_id', 'kanban_synced_at']);
        });
    }
};
