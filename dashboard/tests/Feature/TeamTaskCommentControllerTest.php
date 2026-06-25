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
}
