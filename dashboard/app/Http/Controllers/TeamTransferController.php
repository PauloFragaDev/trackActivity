<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Setting;
use App\Models\Task;
use App\Models\TeamProject;
use App\Models\TeamTask;
use App\Models\TeamTaskCheckbox;
use App\Models\TeamTaskComment;
use App\Models\TeamTaskLabel;
use Illuminate\Http\JsonResponse;

class TeamTransferController extends Controller
{
    public function preview(Task $task): JsonResponse
    {
        abort_unless(Setting::get('team.enabled', true), 403);

        if (!$task->project_id) {
            return response()->json(['project' => null]);
        }

        $project    = Project::find($task->project_id);
        $teamExists = TeamProject::where('code', $project->code)->exists();

        return response()->json([
            'project' => [
                'code'   => $project->code,
                'name'   => $project->name,
                'exists' => $teamExists,
            ],
        ]);
    }

    public function transfer(Task $task): JsonResponse
    {
        abort_unless(Setting::get('team.enabled', true), 403);
        abort_unless(\env('SUPABASE_DB_HOST'), 503);

        // Load personal task with all relations
        $task->load(['checkboxes', 'comments', 'labels', 'project']);

        // 1. Resolve or create team project
        $teamProjectId = null;
        if ($task->project) {
            $teamProject = TeamProject::firstOrCreate(
                ['code' => $task->project->code],
                ['name' => $task->project->name, 'color' => $task->project->color]
            );
            $teamProjectId = $teamProject->id;
        }

        // 2. Create team task
        $teamTask = TeamTask::create([
            'project_id'     => $teamProjectId,
            'title'          => $task->title,
            'description'    => $task->description,
            'status'         => $task->status->value,
            'priority'       => $task->priority?->value,
            'due_date'       => $task->due_date,
            'position'       => (TeamTask::where('status', $task->status->value)->max('position') ?? -1) + 1,
            'created_by_id'  => session('team_member_id') ? (int) session('team_member_id') : null,
        ]);

        // 3. Copy checkboxes
        foreach ($task->checkboxes as $cb) {
            TeamTaskCheckbox::create([
                'task_id'  => $teamTask->id,
                'title'    => $cb->title,
                'checked'  => $cb->checked,
                'position' => $cb->position,
            ]);
        }

        // 4. Copy comments
        foreach ($task->comments as $comment) {
            TeamTaskComment::create([
                'task_id'      => $teamTask->id,
                'body'         => $comment->body,
                'author_name'  => $comment->author_name,
                'author_token' => session('team_member_id') ? (string) session('team_member_id') : $comment->author_token,
            ]);
        }

        // 5. Copy labels (match by name or create)
        foreach ($task->labels as $label) {
            $teamLabel = TeamTaskLabel::firstOrCreate(
                ['title' => $label->title],
                ['color' => $label->color, 'position' => $label->position]
            );
            $teamTask->labels()->attach($teamLabel->id);
        }

        // 6. Archive original (only after successful team creation)
        $task->delete();

        return response()->json(['ok' => true, 'team_task_id' => $teamTask->id]);
    }
}
