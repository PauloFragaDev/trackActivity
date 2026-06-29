<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'supabase';

    public function up(): void
    {
        Schema::connection('supabase')->create('task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->text('body');
            $table->string('author_name')->nullable();
            $table->string('author_token')->nullable();
            $table->timestamps();
            $table->index('author_token');
        });
    }

    public function down(): void
    {
        Schema::connection('supabase')->dropIfExists('task_comments');
    }
};
