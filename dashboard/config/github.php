<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sincronización del tablero Kanban con un GitHub Project
    |--------------------------------------------------------------------------
    |
    | Ver docs/17-github-projects-sync.md. La sincronización solo se activa
    | si hay token y project configurados.
    |
    */

    // PAT classic con scope `project` (uno por persona).
    'token' => env('GITHUB_TOKEN'),

    // Project a sincronizar, en formato "owner/numero" (ej. "PauloFragaDev/3").
    'project' => env('GITHUB_PROJECT'),

    // Endpoint GraphQL de GitHub.
    'api' => env('GITHUB_API', 'https://api.github.com/graphql'),

    /*
    | Mapa entre las columnas locales (TaskStatus) y los nombres de las
    | opciones del campo "Status" del Project en GitHub. Ajústalo a las
    | opciones reales de tu Project.
    */
    'status_map' => [
        'backlog' => 'Backlog',
        'todo'    => 'Todo',
        'doing'   => 'In Progress',
        'done'    => 'Done',
    ],

];
