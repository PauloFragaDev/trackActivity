<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * API REST · solo lectura del catálogo de proyectos. La gestión
 * (alta/edición) sigue siendo via UI del dashboard.
 */
class ProjectController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ProjectResource::collection(Project::orderBy('code')->get());
    }
}
