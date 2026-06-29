<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'supabase';

    public function up(): void
    {
        Schema::connection('supabase')->create('task_checkboxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('title');
            $table->boolean('checked')->default(false);
            $table->integer('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('supabase')->dropIfExists('task_checkboxes');
    }
};
