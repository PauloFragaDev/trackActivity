<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'supabase';

    public function up(): void
    {
        Schema::connection('supabase')->table('tasks', function (Blueprint $table) {
            $table->foreignId('assignee_id')
                ->nullable()
                ->after('project_id')
                ->constrained('team_members')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('supabase')->table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assignee_id');
        });
    }
};
