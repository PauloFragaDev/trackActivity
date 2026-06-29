<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'supabase';

    public function up(): void
    {
        Schema::connection('supabase')->create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('todo');
            $table->string('priority')->nullable();
            $table->date('due_date')->nullable();
            $table->integer('position')->default(0);
            $table->dateTime('completed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['status', 'position']);
        });
    }

    public function down(): void
    {
        Schema::connection('supabase')->dropIfExists('tasks');
    }
};
