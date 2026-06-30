<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Setting;
use App\Models\TeamMember;
use App\Models\TeamProject;
use App\Models\TeamTask;
use App\Models\TeamTaskLabel;
use App\Services\NotificationService;
use App\Services\UserIdentity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeamTaskController extends Controller
{
    public function index(Request $request): View
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

        $projectId  = $request->integer('project') ?: null;
        $assigneeId = $request->integer('assignee') ?: null;

        $tasks = TeamTask::with(['project', 'labels', 'checkboxes', 'comments', 'assignee', 'createdBy'])
            ->when($projectId,  fn ($q) => $q->where('project_id', $projectId))
            ->when($assigneeId, fn ($q) => $q->where('assignee_id', $assigneeId))
            ->orderBy('position')
            ->get()
            ->groupBy(fn (TeamTask $t) => $t->status->value);

        return view('tasks.board', [
            'columns'    => TaskStatus::cases(),
            'priorities' => TaskPriority::cases(),
            'tasks'      => $tasks,
            'projects'   => TeamProject::orderBy('code')->get(),
            'labels'     => TeamTaskLabel::orderBy('position')->orderBy('title')->get(),
            'members'    => TeamMember::orderBy('position')->get(),
            'projectId'  => $projectId,
            'assigneeId' => $assigneeId,
            'priority'   => null,
            'mode'       => 'team',
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $this->validateTask($request);
        $labelIds = $data['label_ids'] ?? [];
        unset($data['label_ids']);
        $data['position'] = (TeamTask::where('status', $data['status'])->max('position') ?? -1) + 1;

        if (session('team_member_id')) {
            $data['created_by_id'] = (int) session('team_member_id');
        }

        $task = TeamTask::create($data);
        $task->labels()->sync($labelIds);

        if ($request->wantsJson()) {
            $task->load(['labels', 'checkboxes', 'comments', 'project', 'assignee', 'createdBy']);
            return response()->json([
                'ok'   => true,
                'html' => view('tasks.partials.card', compact('task'))->render(),
            ]);
        }

        return redirect()->route('team.tasks.index')->with('status', __('tasks.status_created'));
    }

    public function update(Request $request, TeamTask $task): JsonResponse|RedirectResponse
    {
        $data     = $this->validateTask($request);
        $labelIds = $data['label_ids'] ?? [];
        unset($data['label_ids']);

        $oldAssigneeId = $task->assignee_id;

        $task->update($data);
        $task->labels()->sync($labelIds);

        $actorId       = session('team_member_id') ? (int) session('team_member_id') : null;
        $newAssigneeId = array_key_exists('assignee_id', $data) ? (int) $data['assignee_id'] : null;
        if ($newAssigneeId && $newAssigneeId !== $oldAssigneeId && $newAssigneeId !== $actorId) {
            NotificationService::create(
                type:        'assignment',
                taskId:      $task->id,
                recipientId: $newAssigneeId,
                actorId:     $actorId,
                payload:     [
                    'task_title' => $task->title,
                    'actor_name' => session('team_member_name') ?: 'Alguien',
                ],
            );
        }

        if ($request->wantsJson()) {
            $task->load(['labels', 'checkboxes', 'comments', 'project', 'assignee', 'createdBy']);
            return response()->json([
                'ok'   => true,
                'html' => view('tasks.partials.card', compact('task'))->render(),
            ]);
        }

        return redirect()->route('team.tasks.index')->with('status', __('tasks.status_updated'));
    }

    public function destroy(Request $request, TeamTask $task): JsonResponse|RedirectResponse
    {
        $task->delete();

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('team.tasks.index')->with('status', __('tasks.status_archived'));
    }

    public function move(Request $request, TeamTask $task): JsonResponse
    {
        $data = $request->validate([
            'status'   => ['required', Rule::enum(TaskStatus::class)],
            'position' => ['required', 'integer', 'min:0'],
        ]);

        $oldStatus  = $task->status->value;
        $task->status = TaskStatus::from($data['status']);
        $task->save();

        $actorId = session('team_member_id') ? (int) session('team_member_id') : null;
        if ($oldStatus !== $data['status']
            && $task->assignee_id
            && $task->assignee_id !== $actorId
        ) {
            NotificationService::create(
                type:        'status_change',
                taskId:      $task->id,
                recipientId: $task->assignee_id,
                actorId:     $actorId,
                payload:     [
                    'task_title' => $task->title,
                    'old_status' => $oldStatus,
                    'new_status' => $data['status'],
                    'actor_name' => session('team_member_name') ?: 'Alguien',
                ],
            );
        }

        $this->reindex($data['status'], $task->id, (int) $data['position']);
        if ($oldStatus !== $data['status']) {
            $this->reindex($oldStatus);
        }

        return response()->json(['ok' => true]);
    }

    public function peek(): JsonResponse
    {
        $latest = TeamTask::withTrashed()->max('updated_at');
        return response()->json(['latest' => $latest ? (string) $latest : null]);
    }

    private function reindex(string $status, ?int $insertId = null, int $insertAt = 0): void
    {
        $ids = TeamTask::where('status', $status)
            ->when($insertId !== null, fn ($q) => $q->where('id', '!=', $insertId))
            ->orderBy('position')->orderBy('id')
            ->pluck('id')->all();

        if ($insertId !== null) {
            array_splice($ids, max(0, min($insertAt, count($ids))), 0, [$insertId]);
        }

        foreach ($ids as $i => $id) {
            TeamTask::where('id', $id)->update(['position' => $i]);
        }
    }

    private function validateTask(Request $request): array
    {
        return $request->validate([
            'title'       => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'project_id'  => ['nullable', 'integer', 'exists:supabase.projects,id'],
            'assignee_id' => ['nullable', 'integer', 'exists:supabase.team_members,id'],
            'status'      => ['required', Rule::enum(TaskStatus::class)],
            'priority'    => ['nullable', Rule::enum(TaskPriority::class)],
            'due_date'    => ['nullable', 'date'],
            'label_ids'   => ['nullable', 'array'],
            'label_ids.*' => ['integer'],
        ]);
    }
}
