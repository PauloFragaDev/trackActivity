<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            // Sin FK: users vive en sqlite, team_members en la conexión
            // 'supabase' (Postgres) — conexiones distintas no admiten FK
            // cruzada. Validado en la capa de aplicación (seeder / login).
            $table->unsignedBigInteger('team_member_id')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
