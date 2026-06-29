# Notifications Implementation Design

## Goal

Add a real-time notification system to the team Kanban: mentions in task comments (`@nombre` with autocomplete), task assignment, and status changes on assigned tasks. Notifications persist in Supabase and are deleted on read.

## Architecture

Supabase is both the store and the real-time transport. Laravel writes `notifications` rows directly via the existing `supabase` DB connection. The frontend subscribes to `postgres_changes` on that table using the Supabase JS SDK already loaded in the browser. No new services or paid plans required.

**Tech Stack:** Laravel 11, Supabase (PostgreSQL + Realtime), Supabase JS SDK (already in bundle), Tailwind CSS, Toastify-js (already in bundle).

## Global Constraints

- All team DB operations use the `supabase` Eloquent connection (same as `TeamTask`, `TeamMember`, etc.).
- Identity is `MY_MEMBER_ID` from `localStorage`, injected as a `<meta name="member-id">` tag in the layout (same pattern as `user-token`).
- No RLS on the `notifications` table — this is an internal team tool where all members share full task visibility. Client-side filtering by `recipient_id` is sufficient.
- Notifications are deleted (not soft-deleted or marked read) when the user interacts with them. No `read_at` column.
- `CASCADE` deletes handle cleanup: if a task or member is deleted, their notifications disappear automatically.
- Follow existing patterns: `NotificationService` (new) mirrors the style of `ModuleVisibility` and `TrackerManager`. Controllers stay thin.
- Toastify-js for transient toast feedback; no new UI libraries.
- Conventional Commits. No `Co-Authored-By` lines.

---

## Data Model

### Migration: `notifications` table (in `database/migrations/team/`)

```sql
CREATE TABLE notifications (
    id             bigserial PRIMARY KEY,
    recipient_id   bigint NOT NULL REFERENCES team_members(id) ON DELETE CASCADE,
    actor_id       bigint          REFERENCES team_members(id) ON DELETE SET NULL,
    type           text    NOT NULL,  -- 'mention' | 'assignment' | 'status_change'
    task_id        bigint  NOT NULL REFERENCES tasks(id) ON DELETE CASCADE,
    payload        jsonb   NOT NULL DEFAULT '{}'::jsonb,
    created_at     timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX notifications_recipient_idx ON notifications (recipient_id);
```

**`payload` shape by type:**

| type | payload keys |
|------|-------------|
| `mention` | `task_title`, `comment_excerpt` (≤120 chars) |
| `assignment` | `task_title` |
| `status_change` | `task_title`, `old_status`, `new_status` |

### Eloquent Model: `app/Models/Notification.php`

- Connection: `supabase`
- Fillable: `recipient_id`, `actor_id`, `type`, `task_id`, `payload`
- Cast: `payload` → `array`
- No timestamps (`updated_at` not needed; `created_at` is DB default)
- Relations: `recipient()` → `TeamMember`, `actor()` → `TeamMember`, `task()` → `TeamTask`

---

## Backend

### NotificationService (`app/Services/NotificationService.php`)

Single static method used by all controllers:

```php
NotificationService::create(
    type: 'mention',
    taskId: $task->id,
    recipientId: $member->id,
    actorId: $actorId,       // nullable
    payload: ['task_title' => $task->title, 'comment_excerpt' => $excerpt]
);
```

Internally calls `Notification::create([...])`. No queuing — the Supabase write is fast enough for synchronous execution.

### Trigger points

**1. Mention — `TeamTaskCommentController::store()`**

After saving the comment:
1. Parse `$comment->body` with `/\B@([\w ]+)/u` to extract mention strings.
2. For each match, find `TeamMember` where `LOWER(name) = LOWER(mention)`.
3. Exclude the author (`author_token === MY_MEMBER_ID` check skipped server-side; instead exclude `actor_id === recipient_id`).
4. Call `NotificationService::create('mention', ...)` for each resolved member.

**2. Assignment — `TeamTaskController::update()`**

When `assignee_id` changes in the request:
- If new `assignee_id` is not null AND new `assignee_id !== actor_id` (actor is the member making the change, identified by `X-Member-Id` header):
- Call `NotificationService::create('assignment', ...)`.

**3. Status change — `TeamTaskController::move()`**

When a task changes column (status):
- If `$task->assignee_id` is not null AND `$task->assignee_id !== actor_id`:
- Call `NotificationService::create('status_change', ...)` with `old_status` and `new_status` in payload.

### API Endpoints

```
GET    /team/notifications          → list pending for current member (JSON)
DELETE /team/notifications/{id}     → delete one (mark as read)
DELETE /team/notifications          → delete all for current member
```

All three read `X-Member-Id` header to identify the requesting member (same header pattern used in the Kanban board). No new auth middleware needed.

**`NotificationController`** (new, under `App\Http\Controllers`):
- `index()` → `Notification::where('recipient_id', $memberId)->with('actor', 'task')->orderByDesc('created_at')->get()`
- `destroy($id)` → find + delete (guard: `recipient_id === $memberId`)
- `destroyAll()` → `Notification::where('recipient_id', $memberId)->delete()`

---

## Frontend

### Layout changes (`resources/views/layouts/app.blade.php`)

No server-side change needed for member identity. `window.MY_MEMBER_ID` is already set from `localStorage` by the existing identity JS (`app.js` reads `localStorage.getItem('member_id')` on DOMContentLoaded). The notifications module reads `window.MY_MEMBER_ID` directly — same source of truth as the Kanban board.

### Sidebar bell — expanded state

In the expanded sidebar, below the Ctrl K search button, add a `<button id="notif-bell">` row with:
- Bell icon (`x-icon name="bell"`)
- Label "Notificaciones"
- A red badge `<span id="notif-badge">` with the count (hidden when 0)

Clicking opens a dropdown panel anchored below the button (absolute-positioned, `z-50`). The panel contains:
- Header: "Notificaciones" + "Marcar todas como leídas" button (right-aligned)
- Scrollable list of notification items (max-height: 320px)
- Empty state: "Sin notificaciones pendientes" when list is empty

Each notification item shows:
- Actor avatar (colored circle with initials, same style as Kanban member avatars)
- Descriptive text (see copy below)
- Relative time (`x-timestamp` component)
- Clicking the item → navigates to `/team/tasks` with the task modal open (uses existing `#task-{id}` anchor or query param) AND fires `DELETE /team/notifications/{id}`

**Notification copy by type:**
- `mention`: "{actor} te mencionó en *{task_title}*"
- `assignment`: "{actor} te asignó la tarea *{task_title}*"
- `status_change`: "{actor} movió *{task_title}* a {new_status}"

If `actor` is null (member deleted): "Alguien te mencionó en..."

### Sidebar bell — collapsed state

The bell icon is shown in the collapsed sidebar **only when `notifCount > 0`**. It sits below the search icon. A small red dot (not a number) overlays the icon as the unread indicator.

On click, a **speech bubble modal** appears anchored to the right of the bell button with a CSS animation (`transform: scale(0) → scale(1)` from the left edge, 150ms ease-out). The bubble has a left-pointing triangle (`::before` pseudo-element) aligned to the bell center. Inside: same notification list as the expanded panel. Clicking outside or pressing Escape closes it.

### `notifications.js` (new file, imported in `app.js`)

Responsibilities:
1. **Init**: fetch `GET /team/notifications` on page load if `MY_MEMBER_ID` is set → populate badge and list.
2. **Realtime**: subscribe to `postgres_changes` on `notifications` table, `INSERT` event, filter `recipient_id=eq.{MY_MEMBER_ID}`:
   - Increment badge counter.
   - Prepend item to the open panel/bubble if visible.
   - Show Toastify toast: "{actor} te mencionó en {task_title}" (clicking the toast opens the task).
3. **Read**: on item click → `DELETE /team/notifications/{id}` → remove item from DOM → decrement badge.
4. **Read all**: on "Marcar todas" click → `DELETE /team/notifications` → clear list → reset badge to 0.
5. **Collapsed bell visibility**: show/hide the bell based on `notifCount > 0` when sidebar is collapsed.

The Supabase client instance is shared with `kanban-team-realtime.js` (exported from a shared `supabase-client.js` module to avoid creating two connections).

### `@mention autocomplete` in comment textarea

A `MentionAutocomplete` class attached to every `textarea[data-mention]` element:

1. On `input` event: detect if cursor is preceded by `@` with no space since the `@`.
2. Extract the partial name after `@`, filter `window.TEAM_MEMBERS` (array of `{id, name}` already available on the board page).
3. Render a floating `<ul>` dropdown positioned below the `@` character using `getSelection()` / textarea caret position helper.
4. On item click or Enter: replace `@partial` with `@FullName` in textarea value, close dropdown.
5. On Escape or blur: close dropdown.
6. Max 5 results shown.

`window.TEAM_MEMBERS` is already injected by the board view; on non-board pages (where comments might not exist) this feature simply doesn't activate.

---

## Data Flow (end to end)

```
User types "@Paulo fix" → autocomplete shows Paulo → selects
→ textarea contains "@Paulo fix"
→ POST /team/tasks/{id}/comments
→ TeamTaskCommentController parses @Paulo → finds TeamMember id=3
→ NotificationService::create('mention', taskId=7, recipientId=3, actorId=MY_ID, payload)
→ INSERT into Supabase notifications table
→ Supabase Realtime fires postgres_changes INSERT to all subscribers
→ Paulo's browser receives event (recipient_id=3 === MY_MEMBER_ID=3)
→ Badge increments, toast appears: "Ana te mencionó en Fix login bug"
→ Paulo clicks toast → navigates to task, notification deleted
```

---

## What this does NOT include

- Push notifications (browser/OS level) — out of scope for v1
- Email notifications — out of scope
- Notification preferences (mute, digest) — out of scope
- Notifications for personal (SQLite) Kanban tasks — only team Kanban
- RLS on the notifications table — acceptable for internal team use
