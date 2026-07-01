<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La sincronización con GitHub Projects se retiró; estas columnas llevaban
 * desde entonces sin lectores ni escritores.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropUnique(['github_item_id']);
        });
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['github_item_id', 'github_synced_at', 'github_dirty']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('github_item_id')->nullable()->unique();
            $table->dateTime('github_synced_at')->nullable();
            $table->boolean('github_dirty')->default(false);
        });
    }
};
