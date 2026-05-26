<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Endpoints AJAX para los comentarios de una tarea Kanban.
 */
class TaskCommentController extends Controller
{
    public function store(Request $request, Task $task): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $comment = $task->comments()->create($data);

        return response()->json([
            'id'         => $comment->id,
            'body'       => $comment->body,
            'created_at' => $comment->created_at?->toIso8601String(),
        ]);
    }

    public function destroy(Task $task, TaskComment $taskComment): Response
    {
        abort_unless($taskComment->task_id === $task->id, 404);
        $taskComment->delete();

        return response()->noContent();
    }
}
