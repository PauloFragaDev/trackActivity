<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca el origen de un mapeo: null = creado a mano en el editor de proyectos,
 * 'block_correction' = generado automáticamente al corregir un bloque mal
 * atribuido. Sirve para distinguirlos en la UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_mappings', function (Blueprint $table) {
            $table->string('origin')->nullable()->after('enabled');
        });
    }

    public function down(): void
    {
        Schema::table('project_mappings', function (Blueprint $table) {
            $table->dropColumn('origin');
        });
    }
};
