<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectMapping;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(): View
    {
        $projects = Project::query()
            ->withCount(['mappings', 'timeBlocks'])
            ->orderBy('code')
            ->get();

        return view('projects.index', compact('projects'));
    }

    public function create(): View
    {
        $project = new Project([
            'code'  => '',
            'name'  => '',
            'color' => $this->randomColor(),
        ]);
        return view('projects.edit', [
            'project'  => $project,
            'mappings' => collect(),
            'isNew'    => true,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        $project = Project::create($data);

        return redirect()
            ->route('projects.edit', $project)
            ->with('status', __('projects.status_created', ['code' => $project->code]));
    }

    public function edit(Project $project): View
    {
        $project->load(['mappings' => fn ($q) => $q->orderBy('type')->orderBy('pattern')]);
        return view('projects.edit', [
            'project'  => $project,
            'mappings' => $project->mappings,
            'isNew'    => false,
        ]);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $data = $this->validateData($request, $project->id);
        $project->update($data);

        return redirect()
            ->route('projects.edit', $project)
            ->with('status', __('projects.status_updated', ['code' => $project->code]));
    }

    public function destroy(Project $project): RedirectResponse
    {
        $code = $project->code;
        $project->delete();

        return redirect()
            ->route('projects.index')
            ->with('status', __('projects.status_deleted', ['code' => $code]));
    }

    // ─────────────────── Mappings inline ───────────────────

    public function storeMapping(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'type'         => ['required', 'in:' . implode(',', ProjectMapping::TYPES)],
            'pattern'      => ['required', 'string', 'max:255'],
            'is_regex'     => ['sometimes', 'boolean'],
            'weight_bonus' => ['sometimes', 'integer', 'between:-10,10'],
            'enabled'      => ['sometimes', 'boolean'],
        ]);

        ProjectMapping::updateOrCreate(
            ['project_id' => $project->id, 'type' => $data['type'], 'pattern' => $data['pattern']],
            [
                'is_regex'     => (bool) ($data['is_regex']     ?? false),
                'weight_bonus' => (int)  ($data['weight_bonus'] ?? 0),
                'enabled'      => (bool) ($data['enabled']      ?? true),
            ],
        );

        return redirect()
            ->route('projects.edit', $project)
            ->with('status', __('projects.status_mapping_saved', ['type' => $data['type'], 'pattern' => $data['pattern']]));
    }

    public function destroyMapping(Project $project, ProjectMapping $mapping): RedirectResponse
    {
        abort_unless($mapping->project_id === $project->id, 404);
        $mapping->delete();

        return redirect()
            ->route('projects.edit', $project)
            ->with('status', __('projects.status_mapping_deleted'));
    }

    public function toggleMapping(Project $project, ProjectMapping $mapping): RedirectResponse
    {
        abort_unless($mapping->project_id === $project->id, 404);
        $mapping->update(['enabled' => ! $mapping->enabled]);

        return redirect()
            ->route('projects.edit', $project)
            ->with('status', $mapping->enabled ? __('projects.status_mapping_enabled') : __('projects.status_mapping_disabled'));
    }

    // ──────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        $codeRule = 'unique:projects,code' . ($ignoreId ? ",{$ignoreId}" : '');
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
