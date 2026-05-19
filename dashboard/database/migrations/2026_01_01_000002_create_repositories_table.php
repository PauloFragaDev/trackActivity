<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('repositories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('path')->unique();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->dateTime('first_seen_at');
            $table->dateTime('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repositories');
    }
};
