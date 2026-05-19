<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('generated_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('time_block_id')->unique()->constrained('time_blocks')->cascadeOnDelete();
            $table->text('text');
            $table->string('engine');   // template|llm
            $table->boolean('edited_by_user')->default(false);
            $table->dateTime('generated_at');
            $table->dateTime('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_summaries');
    }
};
