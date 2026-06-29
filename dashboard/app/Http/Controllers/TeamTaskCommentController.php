<?php

namespace App\Http\Controllers;

use App\Models\TeamTask;
use App\Models\TeamTaskComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TeamTaskCommentController extends Controller
{
    public function store(Request $request, TeamTask $teamTask): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $comment = $teamTask->comments()->create([
            'body'         => $data['body'],
            'author_name'  => session('team_member_name') ?: null,
            'author_token' => session('team_member_id') ? (string) session('team_member_id') : null,
        ]);

        return response()->json([
            'id'           => $comment->id,
            'body'         => $comment->body,
            'created_at'   => $comment->created_at?->toIso8601String(),
            'author_name'  => $comment->author_name,
            'author_token' => $comment->author_token,
        ]);
    }

    public function destroy(TeamTask $teamTask, TeamTaskComment $comment): Response
    {
        abort_unless($comment->task_id === $teamTask->id, 404);
        $comment->delete();

        return response()->noContent();
    }
}
