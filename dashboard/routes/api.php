<?php

use App\Http\Controllers\Api\KanbanStreamController;
use App\Http\Controllers\Api\KanbanSyncController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TaskLabelController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API REST · single-user, Bearer token estático
|--------------------------------------------------------------------------
|
| Todas las rutas se registran bajo el prefijo /api (gestionado por
| bootstrap/app.php withRouting::api). El middleware `api.token` valida
| el header `Authorization: Bearer <token>` contra `config('app.api_token')`.
|
| Sin token configurado → 503. Sin/incorrecto en la petición → 401.
| Cliente principal previsto: extensión code-kanban (sync por repo).
|
*/

Route::middleware('api.token')->group(function () {

    // Endpoint trivial para que el cliente compruebe credenciales.
    Route::get('/ping', fn () => ['ok' => true, 'service' => 'trackActivity']);

    // Tasks ─ CRUD completo + archivado / restauración / borrado real
    Route::get   ('/tasks',                 [TaskController::class, 'index']);
    Route::post  ('/tasks',                 [TaskController::class, 'store']);
    Route::get   ('/tasks/{task}',          [TaskController::class, 'show']);
    Route::patch ('/tasks/{task}',          [TaskController::class, 'update']);
    Route::delete('/tasks/{task}',          [TaskController::class, 'destroy']);
    Route::post  ('/tasks/{task}/restore',  [TaskController::class, 'restore']);
    Route::delete('/tasks/{task}/force',    [TaskController::class, 'forceDestroy']);

    // Catálogos (solo lectura)
    Route::get('/projects',    [ProjectController::class, 'index']);
    Route::get('/task-labels', [TaskLabelController::class, 'index']);

    // Sync con la extensión code-kanban (un kanban por workspace).
    Route::post('/sync/kanban',        [KanbanSyncController::class,   'store']);
    // SSE: notifica al cliente cuando cambia algo del proyecto en BBDD.
    Route::get ('/sync/kanban/stream', [KanbanStreamController::class, 'stream']);
});
