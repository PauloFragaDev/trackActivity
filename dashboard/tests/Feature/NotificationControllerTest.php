<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\TeamMember;
use App\Models\TeamTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    private TeamMember $member;
    private TeamTask   $task;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--database' => 'supabase', '--path' => 'database/migrations/team']);
        $this->member = TeamMember::create(['name' => 'Ana', 'color' => '#aaa', 'position' => 0]);
        $this->task   = TeamTask::create(['title' => 'Fix', 'status' => 'todo', 'position' => 0]);
        session(['team_member_id' => $this->member->id, 'team_member_name' => $this->member->name]);
    }

    public function test_index_returns_notifications_for_member(): void
    {
        $other = TeamMember::create(['name' => 'Bob', 'color' => '#bbb', 'position' => 1]);
        Notification::create(['recipient_id' => $this->member->id, 'type' => 'mention',    'task_id' => $this->task->id, 'payload' => ['task_title' => 'Fix', 'actor_name' => 'Bob']]);
        Notification::create(['recipient_id' => $other->id,        'type' => 'assignment', 'task_id' => $this->task->id, 'payload' => ['task_title' => 'Fix', 'actor_name' => 'Ana']]);

        $this->getJson('/team/notifications')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.type', 'mention');
    }

    public function test_destroy_deletes_own_notification(): void
    {
        $notif = Notification::create(['recipient_id' => $this->member->id, 'type' => 'mention', 'task_id' => $this->task->id, 'payload' => []]);

        $this->deleteJson("/team/notifications/{$notif->id}")->assertNoContent();

        $this->assertDatabaseMissing('notifications', ['id' => $notif->id], 'supabase');
    }

    public function test_destroy_rejects_other_members_notification(): void
    {
        $other = TeamMember::create(['name' => 'Bob', 'color' => '#bbb', 'position' => 1]);
        $notif = Notification::create(['recipient_id' => $other->id, 'type' => 'mention', 'task_id' => $this->task->id, 'payload' => []]);

        $this->deleteJson("/team/notifications/{$notif->id}")->assertForbidden();
    }

    public function test_destroy_all_deletes_only_own_notifications(): void
    {
        $other = TeamMember::create(['name' => 'Bob', 'color' => '#bbb', 'position' => 1]);
        Notification::create(['recipient_id' => $this->member->id, 'type' => 'mention',    'task_id' => $this->task->id, 'payload' => []]);
        Notification::create(['recipient_id' => $this->member->id, 'type' => 'assignment', 'task_id' => $this->task->id, 'payload' => []]);
        Notification::create(['recipient_id' => $other->id,        'type' => 'mention',    'task_id' => $this->task->id, 'payload' => []]);

        $this->deleteJson('/team/notifications')->assertNoContent();

        $this->assertEquals(0, Notification::where('recipient_id', $this->member->id)->count());
        $this->assertEquals(1, Notification::where('recipient_id', $other->id)->count());
    }

    public function test_index_returns_empty_when_no_session(): void
    {
        session()->forget(['team_member_id', 'team_member_name']);
        $this->getJson('/team/notifications')->assertOk()->assertJsonCount(0);
    }
}
