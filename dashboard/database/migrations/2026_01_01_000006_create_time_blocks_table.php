<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('time_blocks', function (Blueprint $table) {
            $table->id();
            $table->dateTime('starts_at')->unique();
            $table->dateTime('ends_at');
            $table->foreignId('dominant_project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->float('confidence')->nullable();
            $table->string('status');   // auto|edited|merged|split|idle
            $table->json('scoring_snapshot')->nullable();
            $table->dateTime('generated_at');
            $table->dateTime('updated_at');

            $table->index('dominant_project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_blocks');
    }
};
