<?php

use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\TimelineController;
use Illuminate\Support\Facades\Route;

Route::get('/',           [TimelineController::class, 'today'])->name('timeline.today');
Route::get('/day/{date}', [TimelineController::class, 'day'])
    ->where('date', '\d{4}-\d{2}-\d{2}')
    ->name('timeline.day');

Route::get('/week',         [TimelineController::class, 'thisWeek'])->name('timeline.this_week');
Route::get('/week/{week}',  [TimelineController::class, 'week'])
    ->where('week', '\d{4}-W\d{2}')
    ->name('timeline.week');

Route::get('/calendar',       [CalendarController::class, 'current'])->name('calendar.current');
Route::get('/calendar/{ym}',  [CalendarController::class, 'month'])
    ->where('ym', '\d{4}-\d{2}')
    ->name('calendar.month');

Route::get('/export',  [ExportController::class, 'form'])->name('export.form');
Route::post('/export', [ExportController::class, 'download'])->name('export.download');

// Placeholders: se rellenan en commits posteriores
Route::view('/projects', 'placeholder', ['title' => 'Proyectos', 'detail' => 'CRUD en construcción'])->name('projects.index');
Route::view('/help',     'placeholder', ['title' => 'Ayuda',     'detail' => 'Guía en construcción'])->name('help');
