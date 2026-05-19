<?php

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Seeder;

class ProjectsSeeder extends Seeder
{
    public function run(): void
    {
        // Proyectos de ejemplo + el propio repo trackActivity.
        // Edita o reemplaza por los tuyos. Idempotente (updateOrCreate por code).
        $projects = [
            ['code' => 'TRACK',  'name' => 'trackActivity',  'color' => '#10b981'],
            ['code' => 'JASPER', 'name' => 'Jasper',         'color' => '#f43f5e'],
            ['code' => 'YWL',    'name' => 'YourWebLogic',   'color' => '#3b82f6'],
            ['code' => 'TDS',    'name' => 'TDS Platform',   'color' => '#f59e0b'],
            ['code' => 'INT',    'name' => 'Internal Tools', 'color' => '#8b5cf6'],
        ];

        foreach ($projects as $p) {
            Project::updateOrCreate(['code' => $p['code']], $p);
        }
    }
}
