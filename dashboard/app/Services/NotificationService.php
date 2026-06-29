<?php

namespace App\Services;

use App\Models\Notification;

class NotificationService
{
    public static function create(
        string $type,
        int    $taskId,
        int    $recipientId,
        ?int   $actorId,
        array  $payload
    ): void {
        if ($recipientId === $actorId) {
            return;
        }

        Notification::create([
            'type'         => $type,
            'task_id'      => $taskId,
            'recipient_id' => $recipientId,
            'actor_id'     => $actorId,
            'payload'      => $payload,
        ]);
    }
}
