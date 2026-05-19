<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\ProjectMapping;
use Illuminate\Database\Seeder;

class MappingsSeeder extends Seeder
{
    public function run(): void
    {
        // Mappings iniciales. Idempotente: updateOrCreate por (project_id, type, pattern).
        // Edita/borra desde SQL o desde Filament cuando este disponible.
        //
        // El matching es substring case-insensitive (ver SessionBuilder::resolveProjectId).
        // Por eso "ywl-" matchea ywl-admin-dev, ywl-filament y ywl-timesheets.
        $mappings = [
            // trackActivity (este propio repo)
            ['TRACK',  'repository',    'trackActivity'],
            ['TRACK',  'window_title',  'trackActivity'],
            ['TRACK',  'folder',        '/var/www/html/trackActivity'],

            // YWL: pega contra cualquier repo que empiece por "ywl-"
            ['YWL',    'repository',    'ywl-'],
            ['YWL',    'window_title',  'ywl-'],
            ['YWL',    'url_pattern',   'github.com/company/ywl'],

            // TDS
            ['TDS',    'repository',    'tds'],
            ['TDS',    'window_title',  ' tds'],   // espacio inicial evita falsos positivos
            ['TDS',    'url_pattern',   'github.com/company/tds'],

            // Plantillas de ejemplo (no matchean ningun repo concreto)
            ['JASPER', 'repository',    'jasper-api'],
            ['JASPER', 'folder',        '/Projects/jasper-api'],
            ['JASPER', 'url_pattern',   'github.com/company/jasper'],
            ['JASPER', 'email_subject', 'JASPER'],

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
