<?php

namespace App\Http\Controllers;

use App\Models\TeamProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeamProjectController extends Controller
{
    public function index(): View
    {
        $projects = TeamProject::withCount('tasks')->orderBy('code')->get();

        return view('team.projects.index', compact('projects'));
    }

    public function create(): View
    {
        $project = new TeamProject([
            'code'  => '',
            'name'  => '',
            'color' => $this->randomColor(),
        ]);

        return view('team.projects.edit', ['project' => $project, 'isNew' => true]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $project = TeamProject::create($data);

        return redirect()
            ->route('team.projects.edit', $project)
            ->with('status', "Proyecto {$project->code} creado.");
    }

    public function edit(TeamProject $project): View
    {
        return view('team.projects.edit', ['project' => $project, 'isNew' => false]);
    }

    public function update(Request $request, TeamProject $project): RedirectResponse
    {
        $data = $this->validateData($request, $project->id);
        $project->update($data);

        return redirect()
            ->route('team.projects.edit', $project)
            ->with('status', "Proyecto {$project->code} actualizado.");
    }

    public function destroy(TeamProject $project): RedirectResponse
    {
        $code = $project->code;
        $project->delete();

        return redirect()
            ->route('team.projects.index')
            ->with('status', "Proyecto {$code} eliminado.");
    }

    /** @return array<string, mixed> */
    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        $codeRule = 'unique:supabase.projects,code' . ($ignoreId ? ",{$ignoreId}" : '');

        return $request->validate([
            'code'        => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9_-]+$/', $codeRule],
            'name'        => ['required', 'string', 'max:128'],
            'color'       => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'description' => ['nullable', 'string', 'max:1000'],
        ], [
            'code.regex' => 'El code debe ser MAYUSCULAS, numeros, guion o subrayado.',
            'color.regex' => 'El color debe ser hex tipo #RRGGBB.',
        ]);
    }

    private function randomColor(): string
    {
        $palette = ['#10b981', '#3b82f6', '#f59e0b', '#8b5cf6', '#f43f5e', '#06b6d4', '#84cc16', '#ec4899'];
        return $palette[array_rand($palette)];
    }
}
