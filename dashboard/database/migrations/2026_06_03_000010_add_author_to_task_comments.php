<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Añade autoría a los comentarios: nombre visible (snapshot del momento) y
 * token estable del usuario que lo escribió. Pensado para identificar al autor
 * de forma estable cuando los comentarios se sincronicen entre instalaciones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_comments', function (Blueprint $table) {
            $table->string('author_name')->nullable()->after('body');
            $table->string('author_token')->nullable()->after('author_name');
            $table->index('author_token');
        });
    }

    public function down(): void
    {
        Schema::table('task_comments', function (Blueprint $table) {
            $table->dropIndex(['author_token']);
            $table->dropColumn(['author_name', 'author_token']);
        });
    }
};
