<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class NotificationController extends Controller
{
    public function index(): JsonResponse
    {
        $memberId = session('team_member_id');
        if (! $memberId) {
            return response()->json([]);
        }

        $notifications = Notification::where('recipient_id', $memberId)
            ->with('actor', 'task')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($n) => [
                'id'         => $n->id,
                'type'       => $n->type,
                'task_id'    => $n->task_id,
                'task_title' => $n->task?->title ?? ($n->payload['task_title'] ?? ''),
                'payload'    => $n->payload,
                'actor'      => $n->actor ? [
                    'id'       => $n->actor->id,
                    'name'     => $n->actor->name,
                    'color'    => $n->actor->color,
                    'initials' => $n->actor->initials(),
                ] : null,
                'created_at' => $n->created_at?->toIso8601String(),
            ]);

        return response()->json($notifications);
    }

    public function destroy(int $id): Response
    {
        $memberId = session('team_member_id');
        $notif    = Notification::findOrFail($id);

        abort_if($notif->recipient_id !== $memberId, 403);

        $notif->delete();
        return response()->noContent();
    }

    public function destroyAll(): Response
    {
        $memberId = session('team_member_id');
        if ($memberId) {
            Notification::where('recipient_id', $memberId)->delete();
        }
        return response()->noContent();
    }
}
