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
    | Filament admin
    |--------------------------------------------------------------------------
    */
    'filament_enabled' => (bool) env('FILAMENT_ENABLED', false),
];
