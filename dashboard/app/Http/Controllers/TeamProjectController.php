<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Setting;
use App\Models\TeamMember;
use App\Models\TeamProject;
use App\Models\TeamTask;
use App\Models\TeamTaskLabel;
use Illuminate\Http\JsonResponse;
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

    public function board(Request $request, TeamProject $project): View
    {
        if (! session('team_member_id')) {
            $savedId = Setting::get('team.member_id');
            if ($savedId) {
                $member = TeamMember::find((int) $savedId);
                if ($member) {
                    session(['team_member_id' => $member->id, 'team_member_name' => $member->name]);
                } else {
                    Setting::set('team.member_id', null);
                }
            }
        }

        $allValues  = collect(TaskStatus::cases())->map->value->all();
        $savedOrder = Setting::get("team.project.{$project->id}.columns") ?? [];
        $ordered    = collect($savedOrder)->filter(fn($v) => in_array($v, $allValues))->values();
        $missing    = collect($allValues)->diff($ordered)->values();
        $columnOrder = $ordered->merge($missing)->all();

        $assigneeId = $request->integer('assignee') ?: null;

        $tasks = TeamTask::with(['labels', 'checkboxes', 'comments', 'assignee', 'createdBy'])
            ->where('project_id', $project->id)
            ->when($assigneeId, fn($q) => $q->where('assignee_id', $assigneeId))
            ->orderBy('position')
            ->get()
            ->groupBy(fn(TeamTask $t) => $t->status->value);

        return view('tasks.board', [
            'project'         => $project,
            'columns'         => collect($columnOrder)->map(fn($v) => TaskStatus::from($v)),
            'priorities'      => TaskPriority::cases(),
            'tasks'           => $tasks,
            'projects'        => collect([$project]),
            'members'         => TeamMember::orderBy('position')->get(),
            'labels'          => TeamTaskLabel::orderBy('position')->orderBy('title')->get(),
            'mode'            => 'team',
            'columnOrder'     => $columnOrder,
            'columnDraggable' => true,
            'projectId'       => $project->id,
            'assigneeId'      => $assigneeId,
            'priority'        => null,
        ]);
    }

    public function updateColumns(Request $request, TeamProject $project): JsonResponse
    {
        $data    = $request->validate(['columns' => ['required', 'array']]);
        $valid   = collect(TaskStatus::cases())->map->value->all();
        $columns = array_values(array_filter($data['columns'], fn($v) => in_array($v, $valid)));
        Setting::set("team.project.{$project->id}.columns", $columns);
        return response()->json(['ok' => true]);
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
