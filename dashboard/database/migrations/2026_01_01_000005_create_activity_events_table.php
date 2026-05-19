<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('activity_events', function (Blueprint $table) {
            $table->id();
            $table->dateTime('occurred_at')->index();
            $table->string('source');  // window|git|browser|thunderbird|idle
            $table->string('app')->nullable();
            $table->string('title')->nullable();
            $table->string('repo_name')->nullable()->index();
            $table->string('branch')->nullable();
            $table->integer('modified_files')->nullable();
            $table->string('url')->nullable();
            $table->string('subject')->nullable();
            $table->json('metadata')->nullable();

            $table->index(['source', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_events');
    }
};
