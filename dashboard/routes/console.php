<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ──────────────────────────────────────────────
// Jobs programados de trackActivity
// Requiere `php artisan schedule:work` corriendo (o cron del SO).
// ──────────────────────────────────────────────

// Reconstruir bloques sobre las ultimas 2h cada cuarto de hora.
Schedule::command('tracker:rebuild-blocks --since="2 hours ago"')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Regenerar resumenes sobre las ultimas 2h cada cuarto de hora.
Schedule::command('tracker:generate-summaries --since="2 hours ago"')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Limpieza diaria de events muy antiguos.
Schedule::command('tracker:prune-events --older-than="90 days"')
    ->dailyAt('03:00')
    ->withoutOverlapping();
