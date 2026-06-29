<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'supabase';

    public function up(): void
    {
        Schema::connection('supabase')->create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipient_id')->constrained('team_members')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->string('type');
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->json('payload')->default('{}');
            $table->timestampTz('created_at')->useCurrent();

            $table->index('recipient_id');
        });
    }

    public function down(): void
    {
        Schema::connection('supabase')->dropIfExists('notifications');
    }
};
