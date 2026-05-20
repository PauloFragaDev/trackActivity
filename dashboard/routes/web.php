<?php

use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TimeBlockController;
use App\Http\Controllers\TimelineController;
use Illuminate\Support\Facades\Route;

// ─────────────────── Timeline ───────────────────
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

// ─────────────────── Edicion manual de bloques ───────────────────
Route::patch('/blocks',       [TimeBlockController::class, 'update'])->name('blocks.update');
Route::patch('/blocks/reset', [TimeBlockController::class, 'reset'])->name('blocks.reset');

// ─────────────────── Export ───────────────────
Route::get('/export',  [ExportController::class, 'form'])->name('export.form');
Route::post('/export', [ExportController::class, 'download'])->name('export.download');

// ─────────────────── Proyectos (CRUD) ───────────────────
Route::get('/projects',                [ProjectController::class, 'index'])->name('projects.index');
Route::get('/projects/create',         [ProjectController::class, 'create'])->name('projects.create');
Route::post('/projects',               [ProjectController::class, 'store'])->name('projects.store');
Route::get('/projects/{project}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
Route::patch('/projects/{project}',    [ProjectController::class, 'update'])->name('projects.update');
Route::delete('/projects/{project}',   [ProjectController::class, 'destroy'])->name('projects.destroy');

// Mappings anidados bajo el proyecto
Route::post('/projects/{project}/mappings',                   [ProjectController::class, 'storeMapping'])->name('projects.mappings.store');
Route::delete('/projects/{project}/mappings/{mapping}',       [ProjectController::class, 'destroyMapping'])->name('projects.mappings.destroy');
Route::patch('/projects/{project}/mappings/{mapping}/toggle', [ProjectController::class, 'toggleMapping'])->name('projects.mappings.toggle');

// ─────────────────── Ayuda ───────────────────
Route::get('/help', [HelpController::class, 'index'])->name('help');
