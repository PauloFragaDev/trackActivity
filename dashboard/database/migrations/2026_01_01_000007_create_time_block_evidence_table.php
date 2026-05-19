<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('time_block_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('time_block_id')->constrained('time_blocks')->cascadeOnDelete();
            $table->foreignId('activity_event_id')->constrained('activity_events')->cascadeOnDelete();
            $table->integer('weight_contributed');
            $table->text('note')->nullable();

            $table->index('time_block_id');
            $table->index('activity_event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_block_evidence');
    }
};
