<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('project_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('type');   // repository|folder|url_pattern|email_subject|window_title
            $table->string('pattern');
            $table->boolean('is_regex')->default(false);
            $table->integer('weight_bonus')->default(0);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['type', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_mappings');
    }
};
