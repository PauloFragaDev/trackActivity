# Notifications Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a real-time notification system to the team Kanban: @mention autocomplete in comments, plus notifications for assignment and status changes, persisted in Supabase and deleted on read.

**Architecture:** Laravel writes `notifications` rows to Supabase via the existing `supabase` Eloquent connection. The frontend subscribes to `postgres_changes` via the Supabase JS SDK already in the bundle. A sidebar bell shows pending count; expanded sidebar shows a dropdown panel, collapsed sidebar shows a speech-bubble modal (position: fixed, animated).

**Tech Stack:** Laravel 11, Supabase PostgreSQL + Realtime, Supabase JS SDK, Tailwind CSS, Toastify-js.

## Global Constraints

- All team DB operations use the `supabase` Eloquent connection; team models extend `App\Models\TeamModel`.
- Member identity is `session('team_member_id')` (integer) and `session('team_member_name')` (string) — set by `TeamIdentityController`. Use these in controllers, not `X-Member-Id` header.
- Notifications are deleted (not soft-deleted) on read. No `read_at` column.
- `CASCADE` deletes on both `task_id → tasks.id` and `recipient_id → team_members.id` keep the table clean automatically.
- `payload` jsonb must include `actor_name` in all notification types so realtime events can be rendered client-side without a follow-up fetch.
- `payload` shapes (verbatim): `mention` → `{task_title, comment_excerpt, actor_name}` · `assignment` → `{task_title, actor_name}` · `status_change` → `{task_title, old_status, new_status, actor_name}`.
- The sidebar has `overflow-hidden` on `<aside id="sidebar">`. The speech bubble for collapsed state MUST use `position: fixed` positioned via `getBoundingClientRect()` — NOT `position: absolute`.
- Expanded sidebar elements use CSS class `sidebar-full` (hidden when `html.sidebar-collapsed` is set). Collapsed-only elements must NOT use `sidebar-full`.
- Tests for all team features follow the pattern in `TeamTaskCommentControllerTest`: `use RefreshDatabase`, `setUp()` calls `artisan migrate --database=supabase --path=database/migrations/team`.
- Conventional Commits. No `Co-Authored-By` lines.

---

## File Map

| File | Action |
|------|--------|
| `database/migrations/team/2026_06_29_000010_create_notifications_table.php` | Create |
| `app/Models/Notification.php` | Create |
| `app/Services/NotificationService.php` | Create |
| `app/Http/Controllers/NotificationController.php` | Create |
| `routes/web.php` | Modify — add 3 notification routes |
| `app/Http/Controllers/TeamTaskCommentController.php` | Modify — parse @mentions |
| `app/Http/Controllers/TeamTaskController.php` | Modify — assignment + move triggers |
| `resources/views/layouts/app.blade.php` | Modify — Supabase globals + bell HTML |
| `resources/views/tasks/board.blade.php` | Modify — `data-mention` on textarea |
| `resources/css/app.css` | Modify — speech bubble animation + notification styles |
| `resources/js/supabase-client.js` | Create — shared singleton |
| `resources/js/kanban-team-realtime.js` | Modify — use shared client |
| `resources/js/notifications.js` | Create |
| `resources/js/mention-autocomplete.js` | Create |
| `resources/js/app.js` | Modify — import notifications + mention-autocomplete |
| `tests/Feature/NotificationControllerTest.php` | Create |
| `tests/Feature/TeamTaskCommentControllerTest.php` | Modify — add mention tests |
| `tests/Feature/TeamTaskControllerTest.php` | Modify — add notification tests |

---

### Task 1: Migration and Notification model

**Files:**
- Create: `dashboard/database/migrations/team/2026_06_29_000010_create_notifications_table.php`
- Create: `dashboard/app/Models/Notification.php`
- Test: `dashboard/tests/Feature/NotificationModelTest.php`

**Interfaces:**
- Produces: `App\Models\Notification` with `connection = 'supabase'`, fillable `['recipient_id','actor_id','type','task_id','payload']`, cast `payload` → `array`, relations `recipient()`, `actor()`, `task()`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/NotificationModelTest.php`:

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

```bash
cd /var/www/html/trackActivity/dashboard
php artisan test tests/Feature/NotificationModelTest.php
```

Expected: FAIL — `Notification` class not found.

- [ ] **Step 3: Create the migration**

Create `database/migrations/team/2026_06_29_000010_create_notifications_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'supabase';

    public function up(): void
    {
        Schema::connection('supabase')->create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipient_id')->constrained('team_members')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->string('type');
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->jsonb('payload')->default('{}');
            $table->timestampTz('created_at')->useCurrent();

            $table->index('recipient_id');
        });
    }

    public function down(): void
    {
        Schema::connection('supabase')->dropIfExists('notifications');
    }
};
```

- [ ] **Step 4: Create the Notification model**

Create `app/Models/Notification.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends TeamModel
{
    public $timestamps = false;

    protected $fillable = ['recipient_id', 'actor_id', 'type', 'task_id', 'payload'];

    protected $casts = [
        'payload'    => 'array',
        'created_at' => 'datetime',
    ];

    protected $attributes = ['payload' => '{}'];

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'recipient_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'actor_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(TeamTask::class, 'task_id');
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
php artisan test tests/Feature/NotificationModelTest.php
```

Expected: 3 tests, 3 passed.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/team/2026_06_29_000010_create_notifications_table.php \
        app/Models/Notification.php \
        tests/Feature/NotificationModelTest.php
git commit -m "feat(notifications): migración y modelo Notification"
```

---

### Task 2: NotificationService, NotificationController and routes

**Files:**
- Create: `dashboard/app/Services/NotificationService.php`
- Create: `dashboard/app/Http/Controllers/NotificationController.php`
- Modify: `dashboard/routes/web.php`
- Test: `dashboard/tests/Feature/NotificationControllerTest.php`

**Interfaces:**
- Consumes: `App\Models\Notification` (Task 1).
- Produces: `NotificationService::create(string $type, int $taskId, int $recipientId, ?int $actorId, array $payload): void`.
- Produces routes: `GET /team/notifications` → `notification.index`, `DELETE /team/notifications/{id}` → `notification.destroy`, `DELETE /team/notifications` → `notification.destroy_all`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/NotificationControllerTest.php`:

```php
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
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/NotificationControllerTest.php
```

Expected: FAIL — routes not found (404).

- [ ] **Step 3: Create NotificationService**

Create `app/Services/NotificationService.php`:

```php
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
```

- [ ] **Step 4: Create NotificationController**

Create `app/Http/Controllers/NotificationController.php`:

```php
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
```

- [ ] **Step 5: Add routes to web.php**

In `routes/web.php`, inside the `Route::middleware(EnsureTeamEnabled::class)->group(...)` block, add after the existing team routes:

```php
Route::get('/team/notifications',        [\App\Http\Controllers\NotificationController::class, 'index'])->name('notification.index');
Route::delete('/team/notifications/{id}', [\App\Http\Controllers\NotificationController::class, 'destroy'])->name('notification.destroy');
Route::delete('/team/notifications',      [\App\Http\Controllers\NotificationController::class, 'destroyAll'])->name('notification.destroy_all');
```

- [ ] **Step 6: Run tests to verify they pass**

```bash
php artisan test tests/Feature/NotificationControllerTest.php
```

Expected: 5 tests, 5 passed.

- [ ] **Step 7: Commit**

```bash
git add app/Services/NotificationService.php \
        app/Http/Controllers/NotificationController.php \
        routes/web.php \
        tests/Feature/NotificationControllerTest.php
git commit -m "feat(notifications): NotificationService, NotificationController y rutas"
```

---

### Task 3: Mention notifications in TeamTaskCommentController

**Files:**
- Modify: `dashboard/app/Http/Controllers/TeamTaskCommentController.php`
- Modify: `dashboard/tests/Feature/TeamTaskCommentControllerTest.php`

**Interfaces:**
- Consumes: `NotificationService::create()` (Task 2), `App\Models\TeamMember`.
- When a comment body contains `@FullName` matching a team member (case-insensitive), and that member is not the comment author, creates a `mention` notification.

- [ ] **Step 1: Add mention tests to TeamTaskCommentControllerTest**

Append to `tests/Feature/TeamTaskCommentControllerTest.php`:

```php
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
```

- [ ] **Step 2: Run new tests to verify they fail**

```bash
php artisan test tests/Feature/TeamTaskCommentControllerTest.php --filter=mention
```

Expected: FAIL — no notification is created yet.

- [ ] **Step 3: Update TeamTaskCommentController**

Replace the full file `app/Http/Controllers/TeamTaskCommentController.php`:

```php
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
```

- [ ] **Step 4: Run all comment tests to verify they pass**

```bash
php artisan test tests/Feature/TeamTaskCommentControllerTest.php
```

Expected: all tests pass (including the 3 original tests).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/TeamTaskCommentController.php \
        tests/Feature/TeamTaskCommentControllerTest.php
git commit -m "feat(notifications): disparar notificación de mención al guardar comentario"
```

---

### Task 4: Assignment and status-change notifications in TeamTaskController

**Files:**
- Modify: `dashboard/app/Http/Controllers/TeamTaskController.php`
- Modify: `dashboard/tests/Feature/TeamTaskControllerTest.php`

**Interfaces:**
- Consumes: `NotificationService::create()` (Task 2).
- `update()`: when `assignee_id` changes to a non-null value different from the actor, create `assignment` notification.
- `move()`: when status changes and the task has an assignee different from the actor, create `status_change` notification.

- [ ] **Step 1: Write failing tests**

Read the current `tests/Feature/TeamTaskControllerTest.php` to understand the test structure, then append:

```php
    public function test_update_creates_assignment_notification(): void
    {
        $actor    = TeamMember::create(['name' => 'Ana',   'color' => '#aaa', 'position' => 0]);
        $assignee = TeamMember::create(['name' => 'Paulo', 'color' => '#bbb', 'position' => 1]);
        $task     = TeamTask::create(['title' => 'T', 'status' => 'todo', 'position' => 0]);
        $label    = \App\Models\TeamTaskLabel::create(['name' => 'bug', 'color' => '#f00']);
        session(['team_member_id' => $actor->id, 'team_member_name' => $actor->name]);

        $this->patchJson("/team/tasks/{$task->id}", [
            'title'       => 'T',
            'status'      => 'todo',
            'assignee_id' => $assignee->id,
            'label_ids'   => [],
        ])->assertOk();

        $this->assertDatabaseHas('notifications', [
            'recipient_id' => $assignee->id,
            'actor_id'     => $actor->id,
            'type'         => 'assignment',
            'task_id'      => $task->id,
        ], 'supabase');
    }

    public function test_update_does_not_notify_when_actor_assigns_self(): void
    {
        $actor = TeamMember::create(['name' => 'Ana', 'color' => '#aaa', 'position' => 0]);
        $task  = TeamTask::create(['title' => 'T', 'status' => 'todo', 'position' => 0]);
        session(['team_member_id' => $actor->id, 'team_member_name' => $actor->name]);

        $this->patchJson("/team/tasks/{$task->id}", [
            'title'       => 'T',
            'status'      => 'todo',
            'assignee_id' => $actor->id,
            'label_ids'   => [],
        ])->assertOk();

        $this->assertDatabaseCount('notifications', 0, 'supabase');
    }

    public function test_move_creates_status_change_notification(): void
    {
        $actor    = TeamMember::create(['name' => 'Ana',   'color' => '#aaa', 'position' => 0]);
        $assignee = TeamMember::create(['name' => 'Paulo', 'color' => '#bbb', 'position' => 1]);
        $task     = TeamTask::create(['title' => 'T', 'status' => 'todo', 'position' => 0, 'assignee_id' => $assignee->id]);
        session(['team_member_id' => $actor->id, 'team_member_name' => $actor->name]);

        $this->patchJson("/team/tasks/{$task->id}/move", [
            'status'   => 'doing',
            'position' => 0,
        ])->assertOk();

        $this->assertDatabaseHas('notifications', [
            'recipient_id' => $assignee->id,
            'actor_id'     => $actor->id,
            'type'         => 'status_change',
            'task_id'      => $task->id,
        ], 'supabase');
    }
```

Note: the setUp in `TeamTaskControllerTest` must run the notification migration too. Check if it already runs all team migrations; if `setUp()` calls `artisan migrate --path=database/migrations/team`, it will pick up the new migration automatically.

- [ ] **Step 2: Run failing tests**

```bash
php artisan test tests/Feature/TeamTaskControllerTest.php --filter=notification
```

Expected: FAIL — no notifications created.

- [ ] **Step 3: Update TeamTaskController — add imports and update() trigger**

At the top of `app/Http/Controllers/TeamTaskController.php`, add to the use imports:

```php
use App\Services\NotificationService;
```

In the `update()` method, **before** `$task->update($data)`, capture the old assignee:

```php
        $oldAssigneeId = $task->assignee_id;
```

Then, **after** `$task->update($data)`, add:

```php
        $actorId       = session('team_member_id') ? (int) session('team_member_id') : null;
        $newAssigneeId = array_key_exists('assignee_id', $data) ? (int) $data['assignee_id'] : null;
        if ($newAssigneeId && $newAssigneeId !== $oldAssigneeId && $newAssigneeId !== $actorId) {
            NotificationService::create(
                type:        'assignment',
                taskId:      $task->id,
                recipientId: $newAssigneeId,
                actorId:     $actorId,
                payload:     [
                    'task_title' => $task->title,
                    'actor_name' => session('team_member_name') ?: 'Alguien',
                ],
            );
        }
```

- [ ] **Step 4: Update TeamTaskController — move() trigger**

In the `move()` method, after `$task->save()`, add:

```php
        $actorId = session('team_member_id') ? (int) session('team_member_id') : null;
        if ($oldStatus !== $data['status']
            && $task->assignee_id
            && $task->assignee_id !== $actorId
        ) {
            NotificationService::create(
                type:        'status_change',
                taskId:      $task->id,
                recipientId: $task->assignee_id,
                actorId:     $actorId,
                payload:     [
                    'task_title' => $task->title,
                    'old_status' => $oldStatus,
                    'new_status' => $data['status'],
                    'actor_name' => session('team_member_name') ?: 'Alguien',
                ],
            );
        }
```

- [ ] **Step 5: Run all task controller tests**

```bash
php artisan test tests/Feature/TeamTaskControllerTest.php
```

Expected: all tests pass.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/TeamTaskController.php \
        tests/Feature/TeamTaskControllerTest.php
git commit -m "feat(notifications): disparar notificación de asignación y cambio de estado"
```

---

### Task 5: Sidebar bell HTML and CSS

**Files:**
- Modify: `dashboard/resources/views/layouts/app.blade.php`
- Modify: `dashboard/resources/css/app.css`

**Interfaces:**
- Produces DOM elements that `notifications.js` (Task 6) will wire up:
  - `#notif-bell-expanded` — button visible in expanded sidebar
  - `#notif-badge` — red badge span inside the expanded bell
  - `#notif-panel` — dropdown panel (hidden by default), child of `#notif-bell-expanded`'s parent
  - `#notif-list` — `<ul>` inside the panel where items are rendered
  - `#notif-read-all` — "Marcar todas" button inside the panel
  - `#notif-bell-collapsed` — button visible in collapsed sidebar (hidden when 0 notifications)
  - `#notif-dot` — red dot inside the collapsed bell
  - `#notif-bubble` — speech bubble (hidden by default, position: fixed)
  - `#notif-bubble-list` — `<ul>` inside the bubble

- [ ] **Step 1: Add Supabase globals and member ID to the layout**

In `resources/views/layouts/app.blade.php`, find the `<head>` section just before `@vite(...)`. Add:

```html
    @if (($modules['team']['enabled'] ?? false) && config('team.supabase_url'))
    <script>
        window.SUPABASE_URL      = '{{ config("team.supabase_url") }}';
        window.SUPABASE_ANON_KEY = '{{ config("team.supabase_anon_key") }}';
        window.MY_MEMBER_ID      = {{ session('team_member_id') ? (int)session('team_member_id') : 'null' }};
    </script>
    @endif
```

- [ ] **Step 2: Add the expanded bell to the sidebar**

In `resources/views/layouts/app.blade.php`, inside `<nav class="sidebar-full ...">`, after the search button `<button type="button" data-qs-open ...>` block and before the `{{-- Inicio --}}` comment, add:

```html
                {{-- Campana de notificaciones (solo si el módulo equipo está activo) --}}
                @if ($modules['team']['enabled'] ?? false)
                <div class="relative">
                    <button id="notif-bell-expanded" type="button"
                            class="w-full flex items-center gap-1.5 px-2 py-1.5 rounded
                                   text-ink-600 dark:text-ink-300 hover:bg-ink-100 dark:hover:bg-ink-800">
                        <x-icon name="bell" class="w-4 h-4 shrink-0" />
                        <span>Notificaciones</span>
                        <span id="notif-badge"
                              class="hidden ml-auto min-w-[1.1rem] h-[1.1rem] rounded-full
                                     bg-red-500 text-white text-[10px] font-bold
                                     flex items-center justify-center px-1 leading-none">0</span>
                    </button>

                    {{-- Panel dropdown (se muestra/oculta por JS) --}}
                    <div id="notif-panel"
                         class="hidden absolute left-0 top-full mt-1 w-80 z-50
                                rounded-lg border divider shadow-lg
                                bg-[var(--paper)] dark:bg-ink-900">
                        <div class="flex items-center justify-between px-3 py-2 border-b divider">
                            <span class="text-xs font-semibold uppercase tracking-wider text-muted">Notificaciones</span>
                            <button id="notif-read-all" type="button"
                                    class="text-xs text-muted hover:text-ink-900 dark:hover:text-ink-100">
                                Marcar todas como leídas
                            </button>
                        </div>
                        <ul id="notif-list" class="max-h-80 overflow-y-auto divide-y divide-ink-100 dark:divide-ink-800">
                            <li class="px-3 py-4 text-sm text-muted text-center" data-empty>
                                Sin notificaciones pendientes
                            </li>
                        </ul>
                    </div>
                </div>
                @endif
```

- [ ] **Step 3: Add the collapsed bell to the sidebar**

In `resources/views/layouts/app.blade.php`, in the sidebar header `<div class="flex items-center gap-2 p-2 border-b divider">` section, after the toggle button and before the `<a href="{{ route('dashboard') }}">` link (which has `sidebar-full`), add:

```html
                @if ($modules['team']['enabled'] ?? false)
                <button id="notif-bell-collapsed" type="button"
                        class="hidden btn-ghost shrink-0 relative"
                        aria-label="Notificaciones" title="Notificaciones">
                    <x-icon name="bell" class="w-4 h-4" />
                    <span id="notif-dot"
                          class="absolute top-0.5 right-0.5 w-2 h-2 rounded-full bg-red-500 ring-1 ring-white dark:ring-ink-900"></span>
                </button>
                @endif
```

- [ ] **Step 4: Add the speech bubble to the end of the sidebar (before closing `</aside>`)**

```html
            {{-- Speech bubble para el estado colapsado (position: fixed, gestionado por JS) --}}
            @if ($modules['team']['enabled'] ?? false)
            <div id="notif-bubble"
                 class="notif-bubble hidden fixed z-[200] w-80
                        rounded-lg border divider shadow-xl
                        bg-[var(--paper)] dark:bg-ink-900">
                <div class="flex items-center justify-between px-3 py-2 border-b divider">
                    <span class="text-xs font-semibold uppercase tracking-wider text-muted">Notificaciones</span>
                    <button id="notif-bubble-read-all" type="button"
                            class="text-xs text-muted hover:text-ink-900 dark:hover:text-ink-100">
                        Marcar todas como leídas
                    </button>
                </div>
                <ul id="notif-bubble-list" class="max-h-80 overflow-y-auto divide-y divide-ink-100 dark:divide-ink-800">
                    <li class="px-3 py-4 text-sm text-muted text-center" data-empty>
                        Sin notificaciones pendientes
                    </li>
                </ul>
            </div>
            @endif
```

- [ ] **Step 5: Add CSS for speech bubble and notification items**

In `resources/css/app.css`, append:

```css
/* ── Notificaciones ─────────────────────────── */

/* Speech bubble arrow (left-pointing triangle) */
.notif-bubble::before {
    content: '';
    position: absolute;
    left: -8px;
    top: 20px;
    border: 8px solid transparent;
    border-right-color: var(--paper);
    border-left-width: 0;
}

/* Entry animation for the speech bubble */
@keyframes notif-bubble-in {
    from { transform: scale(0); opacity: 0; }
    to   { transform: scale(1); opacity: 1; }
}

.notif-bubble.notif-bubble--visible {
    animation: notif-bubble-in 150ms ease-out forwards;
    transform-origin: left center;
}

/* Notification list item */
.notif-item {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    padding: 0.625rem 0.75rem;
    cursor: pointer;
    transition: background 150ms;
}

.notif-item:hover {
    background: var(--ink-50, #f8fafc);
}

html.dark .notif-item:hover {
    background: rgba(255,255,255,0.04);
}

.notif-avatar {
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 9999px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.625rem;
    font-weight: 700;
    color: #fff;
    flex-shrink: 0;
}

.notif-text {
    flex: 1;
    min-width: 0;
    font-size: 0.8125rem;
    line-height: 1.4;
}

.notif-time {
    font-size: 0.6875rem;
    color: var(--muted, #94a3b8);
    flex-shrink: 0;
    margin-top: 0.125rem;
}
```

- [ ] **Step 6: Build assets and verify visually**

```bash
npm run build
php artisan serve --port=8100
```

Open http://localhost:8100. Log in as a team member. Verify:
- The bell button appears in the expanded sidebar below the search button.
- The `#notif-bubble` exists in the DOM (inspect with devtools) but is hidden.
- The `#notif-bell-collapsed` button exists but is hidden (will show when JS activates it).

- [ ] **Step 7: Commit**

```bash
git add resources/views/layouts/app.blade.php \
        resources/css/app.css
git commit -m "feat(notifications): HTML y CSS para campana en sidebar (expandido y colapsado)"
```

---

### Task 6: Supabase shared client and notifications.js

**Files:**
- Create: `dashboard/resources/js/supabase-client.js`
- Modify: `dashboard/resources/js/kanban-team-realtime.js`
- Create: `dashboard/resources/js/notifications.js`
- Modify: `dashboard/resources/js/app.js`

**Interfaces:**
- Consumes: DOM elements from Task 5 (`#notif-bell-expanded`, `#notif-badge`, etc.).
- Consumes: `window.SUPABASE_URL`, `window.SUPABASE_ANON_KEY`, `window.MY_MEMBER_ID` (injected in layout by Task 5).
- Consumes: `GET /team/notifications`, `DELETE /team/notifications/{id}`, `DELETE /team/notifications` (Task 2).
- Produces: `initNotifications()` exported function, imported in `app.js`.

- [ ] **Step 1: Create supabase-client.js**

Create `resources/js/supabase-client.js`:

```js
import { createClient } from '@supabase/supabase-js';

let _client = null;

export function getSupabaseClient() {
    if (_client) return _client;
    if (!window.SUPABASE_URL || !window.SUPABASE_ANON_KEY) return null;
    _client = createClient(window.SUPABASE_URL, window.SUPABASE_ANON_KEY);
    return _client;
}
```

- [ ] **Step 2: Update kanban-team-realtime.js to use shared client**

Replace `resources/js/kanban-team-realtime.js`:

```js
import { getSupabaseClient } from './supabase-client.js';

let channel = null;

export function initTeamRealtime() {
    if (window.KANBAN_MODE !== 'team') return;

    const supabase = getSupabaseClient();
    if (!supabase) return;

    channel = supabase
        .channel('kanban-team')
        .on(
            'postgres_changes',
            { event: '*', schema: 'public', table: 'tasks' },
            () => {
                const ownMutation = window.__taskMutationAt
                    && (Date.now() - window.__taskMutationAt) < 2000;
                if (ownMutation) return;
                window.location.reload();
            }
        )
        .subscribe();
}

export function destroyTeamRealtime() {
    channel?.unsubscribe();
    channel = null;
}
```

- [ ] **Step 3: Create notifications.js**

Create `resources/js/notifications.js`:

```js
import Toastify from 'toastify-js';
import { getSupabaseClient } from './supabase-client.js';

let notifCount  = 0;
let notifItems  = [];  // cached list

// ── Helpers ─────────────────────────────────────────────

function notifText(n) {
    const actor = n.actor?.name ?? n.payload?.actor_name ?? 'Alguien';
    const title = n.task_title ?? n.payload?.task_title ?? '(tarea)';
    if (n.type === 'mention')       return `${actor} te mencionó en <em>${title}</em>`;
    if (n.type === 'assignment')    return `${actor} te asignó la tarea <em>${title}</em>`;
    if (n.type === 'status_change') {
        const to = n.payload?.new_status ?? '';
        return `${actor} movió <em>${title}</em> a ${to}`;
    }
    return `Nueva notificación en <em>${title}</em>`;
}

function avatarHtml(actor, size = '1.75rem') {
    if (!actor) return `<span class="notif-avatar" style="background:#94a3b8;width:${size};height:${size}">?</span>`;
    return `<span class="notif-avatar" style="background:${actor.color};width:${size};height:${size}">${actor.initials}</span>`;
}

function relativeTime(iso) {
    if (!iso) return '';
    const diff = Math.floor((Date.now() - new Date(iso).getTime()) / 1000);
    if (diff < 60)   return 'ahora';
    if (diff < 3600) return `${Math.floor(diff / 60)}m`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h`;
    return `${Math.floor(diff / 86400)}d`;
}

function buildItemHtml(n) {
    return `
        <li class="notif-item" data-notif-id="${n.id}" data-task-id="${n.task_id}">
            ${avatarHtml(n.actor)}
            <span class="notif-text">${notifText(n)}</span>
            <span class="notif-time">${relativeTime(n.created_at)}</span>
        </li>`;
}

// ── Badge ────────────────────────────────────────────────

function updateBadge(count) {
    notifCount = count;
    const badge  = document.getElementById('notif-badge');
    const dot    = document.getElementById('notif-dot');
    const bell   = document.getElementById('notif-bell-collapsed');

    if (badge) {
        badge.textContent = count;
        badge.classList.toggle('hidden', count === 0);
    }
    if (dot)  dot.classList.toggle('hidden', count === 0);

    const isCollapsed = document.documentElement.classList.contains('sidebar-collapsed');
    if (bell) bell.classList.toggle('hidden', count === 0 || !isCollapsed);
}

// ── List rendering ───────────────────────────────────────

function renderList(items, listEl) {
    if (!listEl) return;
    if (items.length === 0) {
        listEl.innerHTML = '<li class="px-3 py-4 text-sm text-muted text-center" data-empty>Sin notificaciones pendientes</li>';
        return;
    }
    listEl.innerHTML = items.map(buildItemHtml).join('');
    listEl.querySelectorAll('.notif-item').forEach(el => {
        el.addEventListener('click', () => handleRead(el));
    });
}

function refreshLists() {
    renderList(notifItems, document.getElementById('notif-list'));
    renderList(notifItems, document.getElementById('notif-bubble-list'));
}

// ── Read actions ─────────────────────────────────────────

async function handleRead(itemEl) {
    const id     = itemEl.dataset.notifId;
    const taskId = itemEl.dataset.taskId;

    await fetch(`/team/notifications/${id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
    });

    notifItems = notifItems.filter(n => String(n.id) !== String(id));
    updateBadge(notifItems.length);
    refreshLists();

    if (taskId) {
        window.location.href = `/team/tasks#task-${taskId}`;
    }
}

async function handleReadAll() {
    await fetch('/team/notifications', {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
    });
    notifItems = [];
    updateBadge(0);
    refreshLists();
    closeBubble();
    closePanel();
}

// ── Panel (expanded sidebar) ──────────────────────────────

function closePanel() {
    document.getElementById('notif-panel')?.classList.add('hidden');
}

function togglePanel() {
    const panel = document.getElementById('notif-panel');
    if (!panel) return;
    const opening = panel.classList.toggle('hidden');
    if (!opening) refreshLists();
}

// ── Speech bubble (collapsed sidebar) ────────────────────

function closeBubble() {
    const bubble = document.getElementById('notif-bubble');
    if (bubble) {
        bubble.classList.remove('notif-bubble--visible');
        bubble.classList.add('hidden');
    }
}

function openBubble() {
    const bell   = document.getElementById('notif-bell-collapsed');
    const bubble = document.getElementById('notif-bubble');
    if (!bell || !bubble) return;

    const rect = bell.getBoundingClientRect();
    bubble.style.top  = `${rect.top}px`;
    bubble.style.left = `${rect.right + 8}px`;

    refreshLists();
    bubble.classList.remove('hidden');
    // Trigger animation on next frame
    requestAnimationFrame(() => bubble.classList.add('notif-bubble--visible'));
}

function toggleBubble() {
    const bubble = document.getElementById('notif-bubble');
    if (!bubble) return;
    bubble.classList.contains('hidden') ? openBubble() : closeBubble();
}

// ── Init ─────────────────────────────────────────────────

export function initNotifications() {
    if (!window.MY_MEMBER_ID) return;
    if (!window.SUPABASE_URL)  return;

    // Load initial notifications
    fetch('/team/notifications')
        .then(r => r.json())
        .then(items => {
            notifItems = items;
            updateBadge(items.length);
        });

    // Realtime subscription
    const supabase = getSupabaseClient();
    if (supabase) {
        supabase
            .channel(`notifications-${window.MY_MEMBER_ID}`)
            .on('postgres_changes', {
                event:  'INSERT',
                schema: 'public',
                table:  'notifications',
                filter: `recipient_id=eq.${window.MY_MEMBER_ID}`,
            }, (payload) => {
                const n = payload.new;
                notifItems.unshift(n);
                updateBadge(notifItems.length);
                refreshLists();
                // Toast
                const text = notifText(n).replace(/<\/?em>/g, '"');
                Toastify({
                    text,
                    duration:  5000,
                    gravity:   'bottom',
                    position:  'center',
                    className: 'toast',
                    onClick:   () => { window.location.href = `/team/tasks#task-${n.task_id}`; },
                }).showToast();
            })
            .subscribe();
    }

    // Expanded bell click
    document.getElementById('notif-bell-expanded')?.addEventListener('click', togglePanel);

    // Collapsed bell click
    document.getElementById('notif-bell-collapsed')?.addEventListener('click', toggleBubble);

    // Read-all buttons
    document.getElementById('notif-read-all')?.addEventListener('click', handleReadAll);
    document.getElementById('notif-bubble-read-all')?.addEventListener('click', handleReadAll);

    // Close panel/bubble on outside click
    document.addEventListener('click', (e) => {
        if (!e.target.closest('#notif-panel') && !e.target.closest('#notif-bell-expanded')) {
            closePanel();
        }
        if (!e.target.closest('#notif-bubble') && !e.target.closest('#notif-bell-collapsed')) {
            closeBubble();
        }
    });

    // Close on Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') { closePanel(); closeBubble(); }
    });

    // Sync collapsed bell visibility when sidebar toggles
    document.getElementById('sidebar-toggle')?.addEventListener('click', () => {
        const isCollapsed = document.documentElement.classList.contains('sidebar-collapsed');
        const bell        = document.getElementById('notif-bell-collapsed');
        if (bell) bell.classList.toggle('hidden', notifCount === 0 || !isCollapsed);
        closeBubble();
        closePanel();
    });
}
```

- [ ] **Step 4: Import initNotifications in app.js**

In `resources/js/app.js`, at the top with the other imports add:

```js
import { initNotifications } from './notifications.js';
```

Then in the `DOMContentLoaded` handler, after `initTeamRealtime()`, add:

```js
    initNotifications();
```

- [ ] **Step 5: Build and smoke-test**

```bash
npm run build
php artisan serve --port=8100
```

Open http://localhost:8100. Select a team member identity. Open the browser devtools Network tab. Verify:
1. A `GET /team/notifications` request fires on page load.
2. The badge is hidden (0 notifications).
3. Click the bell → the panel opens.
4. Open a second browser window as a different member, post a comment mentioning your first member (`@Ana`) → verify a toast appears in the first window.
5. Click the toast or the notification item → it navigates to the task and the notification disappears.
6. Collapse the sidebar → the expanded bell hides. When a new notification arrives, the collapsed bell appears.
7. Click the collapsed bell → speech bubble appears with animation.

- [ ] **Step 6: Commit**

```bash
git add resources/js/supabase-client.js \
        resources/js/kanban-team-realtime.js \
        resources/js/notifications.js \
        resources/js/app.js
git commit -m "feat(notifications): cliente Supabase compartido y módulo de notificaciones en tiempo real"
```

---

### Task 7: @mention autocomplete in comment textarea

**Files:**
- Create: `dashboard/resources/js/mention-autocomplete.js`
- Modify: `dashboard/resources/views/tasks/board.blade.php`
- Modify: `dashboard/resources/js/app.js`

**Interfaces:**
- Consumes: `window.TEAM_MEMBERS` — array of `{id, name, color, initials}` already injected in `board.blade.php`.
- Attaches to `textarea[data-mention]` elements.
- On `@Name` selection, replaces the partial mention in the textarea with `@FullName` followed by a space.

- [ ] **Step 1: Add data-mention to the comment textarea**

In `resources/views/tasks/board.blade.php`, find the comment textarea. It will be something like:

```html
<textarea name="body" ...></textarea>
```

Add the `data-mention` attribute:

```html
<textarea name="body" data-mention ...></textarea>
```

Search for it with: `grep -n "name=\"body\"\|data-comment\|comment.*textarea\|textarea.*comment" resources/views/tasks/board.blade.php`

- [ ] **Step 2: Create mention-autocomplete.js**

Create `resources/js/mention-autocomplete.js`:

```js
export function initMentionAutocomplete() {
    if (!window.TEAM_MEMBERS?.length) return;

    document.querySelectorAll('textarea[data-mention]').forEach(attach);

    // Also attach to dynamically added textareas (e.g., task modal opened after init)
    const observer = new MutationObserver(() => {
        document.querySelectorAll('textarea[data-mention]:not([data-mention-attached])').forEach(attach);
    });
    observer.observe(document.body, { childList: true, subtree: true });
}

function attach(textarea) {
    textarea.setAttribute('data-mention-attached', '1');

    let dropdown = null;
    let activeIndex = -1;

    function getQuery() {
        const pos  = textarea.selectionStart;
        const text = textarea.value.slice(0, pos);
        const match = text.match(/@([\w\s]*)$/);
        return match ? match[1] : null;
    }

    function getMatches(query) {
        if (query === null) return [];
        const q = query.toLowerCase();
        return window.TEAM_MEMBERS
            .filter(m => m.name.toLowerCase().includes(q))
            .slice(0, 5);
    }

    function removeDropdown() {
        dropdown?.remove();
        dropdown   = null;
        activeIndex = -1;
    }

    function renderDropdown(members) {
        removeDropdown();
        if (!members.length) return;

        dropdown = document.createElement('ul');
        dropdown.className = [
            'absolute z-[300] bg-[var(--paper)] dark:bg-ink-900',
            'border divider rounded shadow-lg py-1 w-48 text-sm',
        ].join(' ');

        members.forEach((m, i) => {
            const li = document.createElement('li');
            li.className = 'flex items-center gap-2 px-3 py-1.5 cursor-pointer hover:bg-ink-100 dark:hover:bg-ink-800';
            li.innerHTML = `
                <span style="background:${m.color}" class="w-5 h-5 rounded-full flex items-center justify-center text-[9px] font-bold text-white">${m.initials}</span>
                <span>${m.name}</span>`;
            li.addEventListener('mousedown', (e) => { e.preventDefault(); selectMember(m); });
            dropdown.appendChild(li);
        });

        // Position dropdown below the cursor
        const rect = textarea.getBoundingClientRect();
        dropdown.style.position = 'fixed';
        dropdown.style.top  = `${rect.bottom + 4}px`;
        dropdown.style.left = `${rect.left}px`;

        document.body.appendChild(dropdown);
        setActive(0);
    }

    function setActive(index) {
        if (!dropdown) return;
        const items = dropdown.querySelectorAll('li');
        items.forEach((li, i) => li.classList.toggle('bg-ink-100', i === index));
        activeIndex = index;
    }

    function selectMember(member) {
        const pos   = textarea.selectionStart;
        const before = textarea.value.slice(0, pos);
        const after  = textarea.value.slice(pos);
        const replaced = before.replace(/@[\w\s]*$/, `@${member.name} `);
        textarea.value = replaced + after;
        textarea.selectionStart = textarea.selectionEnd = replaced.length;
        textarea.dispatchEvent(new Event('input'));
        removeDropdown();
        textarea.focus();
    }

    textarea.addEventListener('input', () => {
        const query   = getQuery();
        const matches = getMatches(query);
        if (matches.length) renderDropdown(matches);
        else removeDropdown();
    });

    textarea.addEventListener('keydown', (e) => {
        if (!dropdown) return;
        const items = dropdown.querySelectorAll('li');
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setActive(Math.min(activeIndex + 1, items.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActive(Math.max(activeIndex - 1, 0));
        } else if (e.key === 'Enter' && activeIndex >= 0) {
            e.preventDefault();
            const members = getMatches(getQuery());
            if (members[activeIndex]) selectMember(members[activeIndex]);
        } else if (e.key === 'Escape') {
            removeDropdown();
        }
    });

    textarea.addEventListener('blur', () => {
        // Small delay to allow mousedown on dropdown to fire first
        setTimeout(removeDropdown, 150);
    });
}
```

- [ ] **Step 3: Import and call initMentionAutocomplete in app.js**

In `resources/js/app.js`, add to imports:

```js
import { initMentionAutocomplete } from './mention-autocomplete.js';
```

In the `DOMContentLoaded` handler, add:

```js
    initMentionAutocomplete();
```

- [ ] **Step 4: Build and smoke-test**

```bash
npm run build
php artisan serve --port=8100
```

Open http://localhost:8100 → navigate to the Team Kanban. Open a task with comments. In the comment textarea, type `@` followed by the first letter of a team member's name. Verify:
1. A dropdown appears with matching member names.
2. Arrow keys navigate the list.
3. Pressing Enter or clicking a name inserts `@FullName ` in the textarea and closes the dropdown.
4. Pressing Escape closes the dropdown without inserting.
5. Post the comment with the mention → verify a notification appears for the mentioned member (using a second browser session as that member).

- [ ] **Step 5: Commit**

```bash
git add resources/js/mention-autocomplete.js \
        resources/js/app.js \
        resources/views/tasks/board.blade.php
git commit -m "feat(notifications): autocompletado de @menciones en textarea de comentarios"
```
