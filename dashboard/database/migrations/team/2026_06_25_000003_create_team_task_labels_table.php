<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'supabase';

    public function up(): void
    {
        Schema::connection('supabase')->create('task_labels', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('color', 20)->default('#64748b');
            $table->integer('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('supabase')->dropIfExists('task_labels');
    }
};
