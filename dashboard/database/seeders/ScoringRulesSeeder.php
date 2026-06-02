<?php

namespace Database\Seeders;

use App\Models\ScoringRule;
use Illuminate\Database\Seeder;

class ScoringRulesSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            [ScoringRule::KIND_VSCODE_IN_REPO,     5, 'VSCode abierto en un repositorio mapeado al proyecto'],
            [ScoringRule::KIND_TERMINAL_IN_REPO,   4, 'Terminal con cwd dentro de un repositorio del proyecto'],
            [ScoringRule::KIND_GIT_MODIFIED,       5, 'Archivos modificados en un repositorio del proyecto'],
            [ScoringRule::KIND_GIT_COMMIT_RECENT,  0, 'Commit reciente (peso 0: disparaba para TODOS los repos con commit reciente, no solo el activo, y contaminaba la atribución)'],
            [ScoringRule::KIND_URL_MATCH,          3, 'URL del navegador que matchea un mapping del proyecto'],
            [ScoringRule::KIND_EMAIL_MATCH,        2, 'Asunto de correo que matchea un mapping del proyecto'],
            [ScoringRule::KIND_WINDOW_TITLE_MATCH, 2, 'Título de ventana genérico que matchea un mapping'],
        ];

        foreach ($rules as [$kind, $weight, $desc]) {
            ScoringRule::updateOrCreate(
                ['signal_kind' => $kind],
                ['weight' => $weight, 'enabled' => true, 'description' => $desc],
            );
        }
    }
}
