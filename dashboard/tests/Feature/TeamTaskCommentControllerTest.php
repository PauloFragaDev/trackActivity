<?php

namespace Tests\Feature;

use App\Models\TeamMember;
use App\Models\TeamTask;
use App\Models\TeamTaskComment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTaskCommentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--database' => 'supabase', '--path' => 'database/migrations/team']);
    }

    public function test_store_creates_comment_with_session_identity(): void
    {
        $member = TeamMember::create(['name' => 'Ana', 'color' => '#000', 'position' => 0]);
        $task   = TeamTask::create(['title' => 'T', 'status' => 'todo', 'position' => 0]);
        session(['team_member_id' => $member->id, 'team_member_name' => $member->name]);

        $this->postJson("/team/tasks/{$task->id}/comments", ['body' => 'Hola equipo'])
            ->assertJsonFragment(['body' => 'Hola equipo', 'author_name' => 'Ana']);

        $this->assertDatabaseHas('task_comments', [
            'body'        => 'Hola equipo',
            'author_name' => 'Ana',
            'author_token' => (string) $member->id,
        ], 'supabase');
    }

    public function test_store_creates_comment_anonymously_without_session(): void
    {
        $task = TeamTask::create(['title' => 'T', 'status' => 'todo', 'position' => 0]);

        $this->postJson("/team/tasks/{$task->id}/comments", ['body' => 'Mensaje anónimo'])
            ->assertJsonFragment(['body' => 'Mensaje anónimo']);
    }

    public function test_destroy_deletes_comment(): void
    {
        $task    = TeamTask::create(['title' => 'T', 'status' => 'todo', 'position' => 0]);
        $comment = TeamTaskComment::create(['task_id' => $task->id, 'body' => 'X']);

        $this->delete("/team/tasks/{$task->id}/comments/{$comment->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('task_comments', ['id' => $comment->id], 'supabase');
    }

    public function test_store_creates_mention_notification(): void
    {
        $author    = TeamMember::create(['name' => 'Ana',   'color' => '#aaa', 'position' => 0]);
        $mentioned = TeamMember::create(['name' => 'Paulo', 'color' => '#bbb', 'position' => 1]);
        $task      = TeamTask::create(['title' => 'Fix bug', 'status' => 'todo', 'position' => 0]);
        session(['team_member_id' => $author->id, 'team_member_name' => $author->name]);

        $this->postJson("/team/tasks/{$task->id}/comments", ['body' => 'Hey @Paulo please review'])
            ->assertOk();

        $this->assertDatabaseHas('notifications', [
            'recipient_id' => $mentioned->id,
            'actor_id'     => $author->id,
            'type'         => 'mention',
            'task_id'      => $task->id,
        ], 'supabase');
    }

    public function test_store_does_not_notify_author_of_self_mention(): void
    {
        $author = TeamMember::create(['name' => 'Ana', 'color' => '#aaa', 'position' => 0]);
        $task   = TeamTask::create(['title' => 'T', 'status' => 'todo', 'position' => 0]);
        session(['team_member_id' => $author->id, 'team_member_name' => $author->name]);

        $this->postJson("/team/tasks/{$task->id}/comments", ['body' => 'I @Ana did this'])
            ->assertOk();

        $this->assertDatabaseCount('notifications', 0, 'supabase');
    }

    public function test_store_does_not_notify_when_no_mention(): void
    {
        $author = TeamMember::create(['name' => 'Ana', 'color' => '#aaa', 'position' => 0]);
        $task   = TeamTask::create(['title' => 'T', 'status' => 'todo', 'position' => 0]);
        session(['team_member_id' => $author->id, 'team_member_name' => $author->name]);

        $this->postJson("/team/tasks/{$task->id}/comments", ['body' => 'Normal comment'])
            ->assertOk();

        $this->assertDatabaseCount('notifications', 0, 'supabase');
    }
}
