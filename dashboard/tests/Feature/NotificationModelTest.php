<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\TeamMember;
use App\Models\TeamTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--database' => 'supabase', '--path' => 'database/migrations/team']);
    }

    public function test_notification_can_be_created(): void
    {
        $recipient = TeamMember::create(['name' => 'Ana', 'color' => '#aaa', 'position' => 0]);
        $actor     = TeamMember::create(['name' => 'Bob', 'color' => '#bbb', 'position' => 1]);
        $task      = TeamTask::create(['title' => 'Fix bug', 'status' => 'todo', 'position' => 0]);

        $notif = Notification::create([
            'recipient_id' => $recipient->id,
            'actor_id'     => $actor->id,
            'type'         => 'mention',
            'task_id'      => $task->id,
            'payload'      => ['task_title' => 'Fix bug', 'comment_excerpt' => 'Hey @Ana', 'actor_name' => 'Bob'],
        ]);

        $this->assertDatabaseHas('notifications', ['type' => 'mention', 'recipient_id' => $recipient->id], 'supabase');
        $this->assertIsArray($notif->payload);
        $this->assertEquals('mention', $notif->type);
    }

    public function test_notification_deleted_when_task_deleted(): void
    {
        $member = TeamMember::create(['name' => 'Ana', 'color' => '#aaa', 'position' => 0]);
        $task   = TeamTask::create(['title' => 'T', 'status' => 'todo', 'position' => 0]);
        Notification::create(['recipient_id' => $member->id, 'type' => 'assignment', 'task_id' => $task->id, 'payload' => []]);

        $task->delete();

        $this->assertDatabaseMissing('notifications', ['task_id' => $task->id], 'supabase');
    }

    public function test_payload_is_cast_to_array(): void
    {
        $member = TeamMember::create(['name' => 'Ana', 'color' => '#aaa', 'position' => 0]);
        $task   = TeamTask::create(['title' => 'T', 'status' => 'todo', 'position' => 0]);
        $notif  = Notification::create([
            'recipient_id' => $member->id,
            'type'         => 'assignment',
            'task_id'      => $task->id,
            'payload'      => ['task_title' => 'T', 'actor_name' => 'Bob'],
        ]);

        $fresh = Notification::find($notif->id);
        $this->assertIsArray($fresh->payload);
        $this->assertEquals('T', $fresh->payload['task_title']);
    }
}
