<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaskLabelResource;
use App\Models\TaskLabel;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * API REST · solo lectura del catálogo global de labels. La gestión
 * (alta/edición/borrado) sigue siendo via /task-labels en la UI.
 */
class TaskLabelController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return TaskLabelResource::collection(
            TaskLabel::orderBy('position')->orderBy('title')->get()
        );
    }
}
