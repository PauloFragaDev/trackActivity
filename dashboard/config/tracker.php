<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Bloques de tiempo
    |--------------------------------------------------------------------------
    */
    'block_minutes'      => (int) env('TRACKER_BLOCK_MINUTES', 15),
    'idle_gap_minutes'   => (int) env('TRACKER_IDLE_GAP_MINUTES', 5),

    /*
    |--------------------------------------------------------------------------
    | Timezone de presentación
    |--------------------------------------------------------------------------
    | La BBDD almacena UTC. Esta zona se aplica en vistas/exports para mostrar
    | horas locales. Independiente de APP_TIMEZONE (que debe quedarse en UTC
    | para consistencia de comparaciones por rango sobre activity_events).
    */
    'display_timezone'   => env('TRACKER_DISPLAY_TIMEZONE', 'Europe/Madrid'),

    /*
    |--------------------------------------------------------------------------
    | Umbrales de confianza
    |--------------------------------------------------------------------------
    */
    'confidence' => [
        'high'   => (float) env('TRACKER_CONFIDENCE_HIGH', 0.65),
        'medium' => (float) env('TRACKER_CONFIDENCE_MEDIUM', 0.35),
    ],

    /*
    |--------------------------------------------------------------------------
    | Generación de resúmenes
    |--------------------------------------------------------------------------
    */
    'summary' => [
        'engine' => env('TRACKER_SUMMARY_ENGINE', 'template'),  // template | llm
        'locale' => env('TRACKER_SUMMARY_LOCALE', 'es'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Control del daemon (botón "Iniciar / Detener tracker" del sidebar)
    |--------------------------------------------------------------------------
    | El dashboard lanza el daemon Python con nohup. Estas rutas se pueden
    | sobreescribir por entorno si la app vive fuera de la convención.
    */
    'bin'         => env('TRACKER_BIN',         dirname(base_path()) . '/tracker/.venv/bin/tracker'),
    'dir'         => env('TRACKER_DIR',         dirname(base_path()) . '/tracker'),
    'config_file' => env('TRACKER_CONFIG_FILE', dirname(base_path()) . '/tracker/config.yml'),
    'pid_file'    => env('TRACKER_PID_FILE',    dirname(base_path()) . '/storage/tracker.pid'),
    'log_file'    => env('TRACKER_LOG_FILE',    dirname(base_path()) . '/storage/logs/tracker.log'),

    /*
    |--------------------------------------------------------------------------
    | Scheduler de Laravel (php artisan schedule:work)
    |--------------------------------------------------------------------------
    | Imprescindible para que tracker:rebuild-blocks/generate-summaries corran
    | cada 15 min; sin él, los eventos crudos no se agregan a time_blocks.
    | El botón del sidebar lo arranca/para junto con el daemon.
    */
    'scheduler' => [
        'pid_file'   => env('SCHEDULER_PID_FILE',   dirname(base_path()) . '/storage/scheduler.pid'),
        'log_file'   => env('SCHEDULER_LOG_FILE',   dirname(base_path()) . '/storage/logs/scheduler.log'),
        'identifier' => env('SCHEDULER_IDENTIFIER', 'schedule:work'),
    ],
];
