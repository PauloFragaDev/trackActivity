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
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\TaskCheckboxController;
use App\Http\Controllers\TaskCommentController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskLabelController;
use App\Http\Controllers\TeamIdentityController;
use App\Http\Controllers\TeamProjectController;
use App\Http\Controllers\TeamTaskController;
use App\Http\Controllers\TeamTaskCommentController;
use App\Http\Middleware\EnsureTeamEnabled;
use App\Http\Middleware\RestoreTeamIdentity;
use App\Http\Controllers\TimeBlockController;
use App\Http\Controllers\TrackerController;
use App\Http\Controllers\TimelineController;
use Illuminate\Support\Facades\Route;

Route::get('/login',   [\App\Http\Controllers\Auth\LoginController::class, 'create'])->name('login');
Route::post('/login',  [\App\Http\Controllers\Auth\LoginController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
Route::post('/logout', [\App\Http\Controllers\Auth\LoginController::class, 'destroy'])->name('logout');

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

Route::post('/notes/images',           [NoteController::class, 'uploadImage'])->name('notes.images.upload');
Route::post('/notes/{note}/move',      [NoteController::class, 'move'])->name('notes.move');

Route::post('/note-folders',                  [NoteFolderController::class, 'store'])->name('note-folders.store');
Route::patch('/note-folders/{noteFolder}',    [NoteFolderController::class, 'update'])->name('note-folders.update');
Route::delete('/note-folders/{noteFolder}',   [NoteFolderController::class, 'destroy'])->name('note-folders.destroy');
Route::post('/note-folders/{noteFolder}/move', [NoteFolderController::class, 'move'])->name('note-folders.move');

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

// Transferencia de tarea personal al tablero del equipo
Route::get('/tasks/{task}/transfer-preview',  [\App\Http\Controllers\TeamTransferController::class, 'preview'])->name('tasks.transfer.preview');
Route::post('/tasks/{task}/transfer-to-team', [\App\Http\Controllers\TeamTransferController::class, 'transfer'])->name('tasks.transfer.store');

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
$teamMiddleware = [EnsureTeamEnabled::class, RestoreTeamIdentity::class];
if (config('app.mode') === 'team_only') {
    $teamMiddleware[] = 'auth';
}

Route::middleware($teamMiddleware)->group(function () {
    Route::get('/team/tasks',                [TeamTaskController::class, 'index'])->name('team.tasks.index');
    Route::get('/team/tasks/peek',           [TeamTaskController::class, 'peek'])->name('team.tasks.peek');
    Route::post('/team/tasks',               [TeamTaskController::class, 'store'])->name('team.tasks.store');
    Route::patch('/team/tasks/{task}',       [TeamTaskController::class, 'update'])->name('team.tasks.update');
    Route::patch('/team/tasks/{task}/move',  [TeamTaskController::class, 'move'])->name('team.tasks.move');
    Route::delete('/team/tasks/{task}',      [TeamTaskController::class, 'destroy'])->name('team.tasks.destroy');


    Route::get('/team/projects',                  [TeamProjectController::class, 'index'])->name('team.projects.index');
    Route::get('/team/projects/create',           [TeamProjectController::class, 'create'])->name('team.projects.create');
    Route::post('/team/projects',                 [TeamProjectController::class, 'store'])->name('team.projects.store');
    Route::get('/team/projects/{project}/edit',   [TeamProjectController::class, 'edit'])->name('team.projects.edit');
    Route::patch('/team/projects/{project}',      [TeamProjectController::class, 'update'])->name('team.projects.update');
    Route::delete('/team/projects/{project}',     [TeamProjectController::class, 'destroy'])->name('team.projects.destroy');
    Route::get('/team/projects/{project}/board',  [TeamProjectController::class, 'board'])->name('team.projects.board');
    Route::patch('/team/projects/{project}/columns',[TeamProjectController::class, 'updateColumns'])->name('team.projects.columns');

    Route::post('/team/identity',   [TeamIdentityController::class, 'store'])->name('team.identity.store');
    Route::delete('/team/identity', [TeamIdentityController::class, 'destroy'])->name('team.identity.destroy');

    Route::post('/team/tasks/{teamTask}/comments',            [TeamTaskCommentController::class, 'store'])->name('team.tasks.comments.store');
    Route::delete('/team/tasks/{teamTask}/comments/{comment}', [TeamTaskCommentController::class, 'destroy'])->name('team.tasks.comments.destroy');

    Route::post('/team/tasks/{teamTask}/checkboxes',                   [\App\Http\Controllers\TeamTaskCheckboxController::class, 'store'])->name('team.tasks.checkboxes.store');
    Route::patch('/team/tasks/{teamTask}/checkboxes/{checkbox}',       [\App\Http\Controllers\TeamTaskCheckboxController::class, 'update'])->name('team.tasks.checkboxes.update');
    Route::delete('/team/tasks/{teamTask}/checkboxes/{checkbox}',      [\App\Http\Controllers\TeamTaskCheckboxController::class, 'destroy'])->name('team.tasks.checkboxes.destroy');

    Route::get('/team/notifications',        [NotificationController::class, 'index'])->name('notification.index');
    Route::delete('/team/notifications/{id}', [NotificationController::class, 'destroy'])->name('notification.destroy');
    Route::delete('/team/notifications',      [NotificationController::class, 'destroyAll'])->name('notification.destroy_all');
});

// En modo team_only (instancia pública del Kanban) redirigir la raíz al equipo.
// Va al final del fichero a propósito: Route::redirect() registra con
// Route::any(), y Laravel indexa las rutas por [método][uri], así que una
// ruta posterior con el mismo uri pisa a una anterior (p. ej. Route::get('/',
// ...timeline.today...) definida arriba). Registrando esto al final nos
// aseguramos de que gane sobre cualquier ruta '/' o '/tasks' ya registrada.
if (config('app.mode') === 'team_only') {
    Route::redirect('/tasks', '/team/tasks');
    Route::redirect('/', '/team/tasks');
}
