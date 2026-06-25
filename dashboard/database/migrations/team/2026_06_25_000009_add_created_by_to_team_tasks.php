<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'supabase';

    public function up(): void
    {
        Schema::connection('supabase')->table('tasks', function (Blueprint $table) {
            $table->foreignId('created_by_id')
                ->nullable()
                ->after('assignee_id')
                ->constrained('team_members')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('supabase')->table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_id');
        });
    }
};
