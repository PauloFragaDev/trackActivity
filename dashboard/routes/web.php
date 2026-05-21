<?php

use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\ManualEntryController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\NoteFolderController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TimeBlockController;
use App\Http\Controllers\TimelineController;
use Illuminate\Support\Facades\Route;

// ─────────────────── Inicio ───────────────────
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

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

// ─────────────────── Entradas manuales (reuniones, correcciones) ───────────────────
Route::post('/manual-entries',                  [ManualEntryController::class, 'store'])->name('manual-entries.store');
Route::patch('/manual-entries/{manualEntry}',   [ManualEntryController::class, 'update'])->name('manual-entries.update');
Route::delete('/manual-entries/{manualEntry}',  [ManualEntryController::class, 'destroy'])->name('manual-entries.destroy');

// ─────────────────── Export ───────────────────
Route::get('/export',  [ExportController::class, 'form'])->name('export.form');
Route::post('/export', [ExportController::class, 'download'])->name('export.download');

// ─────────────────── Datos (copias, restaurar, exportar) ───────────────────
Route::get('/data',                [DataController::class, 'index'])->name('data.index');
Route::post('/data/backup',        [DataController::class, 'backupNow'])->name('data.backup');
Route::get('/data/backup/{name}',  [DataController::class, 'downloadBackup'])->name('data.backup.download');
Route::post('/data/restore',       [DataController::class, 'restore'])->name('data.restore');
Route::get('/data/export/notes',   [DataController::class, 'exportNotes'])->name('data.export.notes');
Route::get('/data/export/data',    [DataController::class, 'exportData'])->name('data.export.data');

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

// ─────────────────── Notas ───────────────────
Route::get('/notes',           [NoteController::class, 'index'])->name('notes.index');
Route::get('/notes/quick',     [NoteController::class, 'quick'])->name('notes.quick');
Route::post('/notes',          [NoteController::class, 'store'])->name('notes.store');
Route::delete('/notes/trash',  [NoteController::class, 'emptyTrash'])->name('notes.trash.empty');
Route::patch('/notes/{note}',        [NoteController::class, 'update'])->name('notes.update');
Route::patch('/notes/{note}/pin',    [NoteController::class, 'togglePin'])->name('notes.pin');
Route::patch('/notes/{id}/restore',  [NoteController::class, 'restore'])->name('notes.restore');
Route::delete('/notes/{note}',       [NoteController::class, 'destroy'])->name('notes.destroy');

Route::post('/note-folders',                [NoteFolderController::class, 'store'])->name('note-folders.store');
Route::patch('/note-folders/{noteFolder}',  [NoteFolderController::class, 'update'])->name('note-folders.update');
Route::delete('/note-folders/{noteFolder}', [NoteFolderController::class, 'destroy'])->name('note-folders.destroy');

// ─────────────────── Tareas (Kanban) ───────────────────
Route::get('/tasks',                [TaskController::class, 'index'])->name('tasks.index');
Route::post('/tasks',               [TaskController::class, 'store'])->name('tasks.store');
Route::patch('/tasks/{task}',       [TaskController::class, 'update'])->name('tasks.update');
Route::patch('/tasks/{task}/move',  [TaskController::class, 'move'])->name('tasks.move');
Route::delete('/tasks/{task}',      [TaskController::class, 'destroy'])->name('tasks.destroy');

// ─────────────────── Ayuda ───────────────────
Route::get('/help', [HelpController::class, 'index'])->name('help');
