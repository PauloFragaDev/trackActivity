<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\ProjectMapping;
use Illuminate\Database\Seeder;

class MappingsSeeder extends Seeder
{
    public function run(): void
    {
        // Mappings de ejemplo. Edítalos desde Filament o por seeder propio.
        $mappings = [
            ['JASPER', 'repository',    'jasper-api'],
            ['JASPER', 'folder',        '/Projects/jasper-api'],
            ['JASPER', 'url_pattern',   'github.com/company/jasper'],
            ['JASPER', 'email_subject', 'JASPER'],

            ['YWL',    'repository',    'ywl-webapp'],
            ['YWL',    'url_pattern',   'github.com/company/ywl'],

            ['TDS',    'repository',    'tds-platform'],
            ['TDS',    'url_pattern',   'github.com/company/tds'],

            ['INT',    'repository',    'internal-tools'],
        ];

        foreach ($mappings as [$code, $type, $pattern]) {
            $project = Project::where('code', $code)->first();
            if (! $project) {
                continue;
            }

            ProjectMapping::updateOrCreate(
                ['project_id' => $project->id, 'type' => $type, 'pattern' => $pattern],
                ['is_regex' => false, 'weight_bonus' => 0, 'enabled' => true],
            );
        }
    }
}
