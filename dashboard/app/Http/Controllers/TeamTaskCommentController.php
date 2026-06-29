<?php

namespace App\Http\Controllers;

use App\Models\TeamMember;
use App\Models\TeamTask;
use App\Models\TeamTaskComment;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TeamTaskCommentController extends Controller
{
    public function store(Request $request, TeamTask $teamTask): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $actorId = session('team_member_id') ? (int) session('team_member_id') : null;

        $comment = $teamTask->comments()->create([
            'body'         => $data['body'],
            'author_name'  => session('team_member_name') ?: null,
            'author_token' => $actorId ? (string) $actorId : null,
        ]);

        $this->dispatchMentionNotifications($teamTask, $data['body'], $actorId);

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

    private function dispatchMentionNotifications(TeamTask $task, string $body, ?int $actorId): void
    {
        $actorName = session('team_member_name') ?: 'Alguien';
        $excerpt   = mb_substr($body, 0, 120);

        TeamMember::all()->each(function (TeamMember $member) use ($task, $body, $actorId, $actorName, $excerpt) {
            $pattern = '/@' . preg_quote($member->name, '/') . '(?!\w)/iu';
            if (preg_match($pattern, $body)) {
                NotificationService::create(
                    type:        'mention',
                    taskId:      $task->id,
                    recipientId: $member->id,
                    actorId:     $actorId,
                    payload:     [
                        'task_title'      => $task->title,
                        'comment_excerpt' => $excerpt,
                        'actor_name'      => $actorName,
                    ],
                );
            }
        });
    }
}
