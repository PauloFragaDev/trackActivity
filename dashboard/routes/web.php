<?php

use App\Http\Controllers\ActivityEventController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\ManualEntryController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\NoteFolderController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\TaskCheckboxController;
use App\Http\Controllers\TaskCommentController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskLabelController;
use App\Http\Controllers\TeamMemberController;
use App\Http\Controllers\TeamTaskController;
use App\Http\Controllers\TimeBlockController;
use App\Http\Controllers\TrackerController;
use App\Http\Controllers\TimelineController;
use Illuminate\Support\Facades\Route;

// En Render (APP_MODE=team_only) redirigir la raíz del kanban al equipo
if (env('APP_MODE') === 'team_only') {
    Route::redirect('/tasks', '/team/tasks');
}

// ─────────────────── Inicio ───────────────────
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

// Edición manual de un activity_event (desde la lista de evidencia del timeline).
Route::patch('/activity-events/{activityEvent}', [ActivityEventController::class, 'update'])->name('activity-events.update');

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
Route::get('/reports',        [ReportsController::class, 'index'])->name('reports.index');
Route::get('/insights',       [\App\Http\Controllers\InsightsController::class, 'index'])->name('insights.index');
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
Route::get('/tasks/peek',           [TaskController::class, 'peek'])->name('tasks.peek');
Route::get('/tasks/archived',       [TaskController::class, 'archived'])->name('tasks.archived');
Route::post('/tasks',               [TaskController::class, 'store'])->name('tasks.store');
// Endpoints en lote ANTES de los parametrizados: si no, "/tasks/bulk-force"
// lo capturaría "/tasks/{task}" con task="bulk-force".
Route::post('/tasks/bulk-restore',  [TaskController::class, 'bulkRestore'])->name('tasks.bulk-restore');
Route::delete('/tasks/bulk-force',  [TaskController::class, 'bulkForceDestroy'])->name('tasks.bulk-force-destroy');
Route::patch('/tasks/{task}',       [TaskController::class, 'update'])->name('tasks.update');
Route::patch('/tasks/{task}/move',  [TaskController::class, 'move'])->name('tasks.move');
Route::delete('/tasks/{task}',      [TaskController::class, 'destroy'])->name('tasks.destroy');
Route::post('/tasks/{task}/restore',[TaskController::class, 'restore'])->name('tasks.restore');
Route::delete('/tasks/{task}/force',[TaskController::class, 'forceDestroy'])->name('tasks.force-destroy');

// Paleta de etiquetas del tablero
Route::get('/task-labels',                  [TaskLabelController::class, 'index'])->name('task-labels.index');
Route::post('/task-labels',                 [TaskLabelController::class, 'store'])->name('task-labels.store');
Route::patch('/task-labels/{taskLabel}',    [TaskLabelController::class, 'update'])->name('task-labels.update');
Route::delete('/task-labels/{taskLabel}',   [TaskLabelController::class, 'destroy'])->name('task-labels.destroy');

// Subtareas (checkboxes) — endpoints AJAX desde el modal de edición
Route::post('/tasks/{task}/checkboxes',                  [TaskCheckboxController::class, 'store'])->name('task-checkboxes.store');
Route::patch('/tasks/{task}/checkboxes/{taskCheckbox}',  [TaskCheckboxController::class, 'update'])->name('task-checkboxes.update');
Route::delete('/tasks/{task}/checkboxes/{taskCheckbox}', [TaskCheckboxController::class, 'destroy'])->name('task-checkboxes.destroy');

// Comentarios — endpoints AJAX desde el modal de edición
Route::post('/tasks/{task}/comments',                  [TaskCommentController::class, 'store'])->name('task-comments.store');
Route::delete('/tasks/{task}/comments/{taskComment}',  [TaskCommentController::class, 'destroy'])->name('task-comments.destroy');

// ─────────────────── Control del tracker ───────────────────
Route::post('/tracker/toggle', [TrackerController::class, 'toggle'])->name('tracker.toggle');

// ─────────────────── Pomodoro (página única) ───────────────────
// El timer corre 100% client-side (localStorage). Solo necesitamos el
// punto de entrada que entrega la config para que pomodoro.js arranque.
Route::get('/pomodoro', [\App\Http\Controllers\PomodoroController::class, 'index'])->name('pomodoro.index');

// ─────────────────── Ajustes (hub) ───────────────────
// /settings redirige a /settings/general. Las "viejas" páginas de
// configuración (proyectos, etiquetas, export, data) mantienen sus URLs;
// el cambio es solo de navegación (mini-sidebar en layouts.settings).
Route::get('/settings',           [\App\Http\Controllers\SettingsController::class, 'index'])->name('settings.index');
Route::get('/settings/general',     [\App\Http\Controllers\SettingsController::class, 'general'])->name('settings.general');
Route::post('/settings/general',    [\App\Http\Controllers\SettingsController::class, 'saveGeneral'])->name('settings.general.save');
Route::get('/settings/appearance',  [\App\Http\Controllers\SettingsController::class, 'appearance'])->name('settings.appearance');
Route::post('/settings/appearance', [\App\Http\Controllers\SettingsController::class, 'saveAppearance'])->name('settings.appearance.save');
Route::get('/settings/pomodoro',    [\App\Http\Controllers\SettingsController::class, 'pomodoro'])->name('settings.pomodoro');
Route::post('/settings/pomodoro',   [\App\Http\Controllers\SettingsController::class, 'savePomodoro'])->name('settings.pomodoro.save');
Route::get('/settings/sync',        [\App\Http\Controllers\SettingsController::class, 'sync'])->name('settings.sync');
Route::post('/settings/sync',       [\App\Http\Controllers\SettingsController::class, 'saveSync'])->name('settings.sync.save');

// ─────────────────── Ayuda ───────────────────
Route::get('/help', [HelpController::class, 'index'])->name('help');

// ─────────────────── Ajustes — integraciones ───────────────────
Route::get('/settings/integrations',  [\App\Http\Controllers\SettingsController::class, 'integrations'])->name('settings.integrations');
Route::post('/settings/integrations', [\App\Http\Controllers\SettingsController::class, 'saveIntegrations'])->name('settings.integrations.save');

// ─────────────────── Equipo (Kanban compartido, Supabase) ───────────────────
Route::get('/team/tasks',                [TeamTaskController::class, 'index'])->name('team.tasks.index');
Route::get('/team/tasks/peek',           [TeamTaskController::class, 'peek'])->name('team.tasks.peek');
Route::post('/team/tasks',               [TeamTaskController::class, 'store'])->name('team.tasks.store');
Route::patch('/team/tasks/{task}',       [TeamTaskController::class, 'update'])->name('team.tasks.update');
Route::patch('/team/tasks/{task}/move',  [TeamTaskController::class, 'move'])->name('team.tasks.move');
Route::delete('/team/tasks/{task}',      [TeamTaskController::class, 'destroy'])->name('team.tasks.destroy');

Route::get('/team/members',                  [TeamMemberController::class, 'index'])->name('team.members.index');
Route::post('/team/members',                 [TeamMemberController::class, 'store'])->name('team.members.store');
Route::patch('/team/members/{teamMember}',   [TeamMemberController::class, 'update'])->name('team.members.update');
Route::delete('/team/members/{teamMember}',  [TeamMemberController::class, 'destroy'])->name('team.members.destroy');
