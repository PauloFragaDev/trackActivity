# Kanban Multi-Usuario con Supabase — Plan de Implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Añadir un Kanban compartido (modo Equipo) con Supabase como backend y tiempo real, manteniendo el Kanban personal existente (SQLite), con toggle Personal/Equipo en la misma interfaz.

**Architecture:** La app local conecta a dos bases de datos: SQLite (personal) y Supabase PostgreSQL (equipo). El board muestra un toggle que cambia entre ambos modos. El modo Equipo usa Supabase Realtime (JS) para broadcast instantáneo de cambios a todos los clientes. El servidor Render aloja la app para acceso web al Kanban del equipo; un cron de GitHub Actions lo mantiene despierto.

**Tech Stack:** Laravel 11 · PHP 8.4 · SQLite (personal) · Supabase PostgreSQL (equipo) · `@supabase/supabase-js` · SortableJS · Vite · PHPUnit · Render · GitHub Actions

## Global Constraints

- App Laravel en `/dashboard` dentro del repo raíz.
- Conexión por defecto: `sqlite`. Nueva conexión: `supabase` (driver `pgsql`).
- En tests, la conexión `supabase` usa SQLite `:memory:` via env vars en `phpunit.xml`.
- Modelos del equipo extienden `TeamModel` (connection = `supabase`).
- Modelos personales (`Task`, `Project`, etc.) no se tocan.
- Commits: Conventional Commits, sin `Co-Authored-By`.
- Settings globales via `Setting::get/set` (tabla `settings`, SQLite).
- Keys de Supabase en `.env`: `SUPABASE_DB_*` (server-side), `SUPABASE_ANON_KEY` (frontend).

---

## Mapa de ficheros

| Acción | Fichero |
|--------|---------|
| Modificar | `config/database.php` |
| Modificar | `phpunit.xml` |
| Modificar | `.env.example` |
| Crear | `app/Models/TeamModel.php` |
| Crear | `app/Models/TeamTask.php` |
| Crear | `app/Models/TeamProject.php` |
| Crear | `app/Models/TeamMember.php` |
| Crear | `app/Models/TeamTaskLabel.php` |
| Crear | `app/Models/TeamTaskCheckbox.php` |
| Crear | `app/Models/TeamTaskComment.php` |
| Crear | `database/migrations/team/` (8 migraciones) |
| Crear | `app/Http/Controllers/TeamTaskController.php` |
| Crear | `app/Http/Controllers/TeamMemberController.php` |
| Modificar | `routes/web.php` |
| Modificar | `resources/views/tasks/board.blade.php` |
| Modificar | `resources/js/kanban.js` |
| Crear | `resources/js/kanban-team.js` |
| Modificar | `resources/js/app.js` |
| Modificar | `app/Http/Controllers/SettingsController.php` |
| Crear | `resources/views/settings/integrations.blade.php` |
| Modificar | `resources/views/layouts/settings.blade.php` (añadir enlace) |
| Crear | `tests/Feature/TeamTaskControllerTest.php` |
| Crear | `tests/Feature/TeamMemberControllerTest.php` |
| Crear | `render.yaml` |
| Crear | `.github/workflows/keep-alive.yml` |

---

### Task 1: Conexión Supabase en Laravel

**Files:**
- Modify: `dashboard/config/database.php`
- Modify: `dashboard/phpunit.xml`
- Modify: `dashboard/.env.example`

**Interfaces:**
- Produces: conexión `'supabase'` disponible en toda la app via `DB::connection('supabase')` y en modelos con `$connection = 'supabase'`.

- [ ] **Step 1: Añadir la conexión `supabase` a `config/database.php`**

Dentro del array `'connections'`, después de `'pgsql'`, añade:

```php
'supabase' => [
    'driver'         => env('SUPABASE_DB_DRIVER', 'pgsql'),
    'host'           => env('SUPABASE_DB_HOST', '127.0.0.1'),
    'port'           => env('SUPABASE_DB_PORT', '5432'),
    'database'       => env('SUPABASE_DB_DATABASE', 'postgres'),
    'username'       => env('SUPABASE_DB_USERNAME', 'postgres'),
    'password'       => env('SUPABASE_DB_PASSWORD', ''),
    'charset'        => 'utf8',
    'prefix'         => '',
    'prefix_indexes' => true,
    'search_path'    => 'public',
    'sslmode'        => env('SUPABASE_DB_SSLMODE', 'require'),
],
```

- [ ] **Step 2: Aislar la conexión `supabase` en tests (usa SQLite `:memory:`)**

En `dashboard/phpunit.xml`, dentro de `<php>`, añade tras las líneas existentes de DB:

```xml
<!-- Supabase: en tests usa SQLite :memory: para no depender de red -->
<env name="SUPABASE_DB_DRIVER"   value="sqlite"/>
<env name="SUPABASE_DB_DATABASE" value=":memory:"/>
```

- [ ] **Step 3: Documentar las vars en `.env.example`**

Añade al final de `dashboard/.env.example`:

```
# ──────────────────────────────────────────────
# Supabase (Kanban de equipo)
# Obtén estos valores en: Supabase Dashboard → Settings → Database
# ──────────────────────────────────────────────
SUPABASE_DB_HOST=db.<project-ref>.supabase.co
SUPABASE_DB_PORT=5432
SUPABASE_DB_DATABASE=postgres
SUPABASE_DB_USERNAME=postgres
SUPABASE_DB_PASSWORD=your-db-password
SUPABASE_DB_SSLMODE=require

# Clave pública (anon) — se pasa al frontend JS para Realtime
SUPABASE_ANON_KEY=your-anon-key

# Clave privada (service_role) — solo en servidor, nunca al frontend
SUPABASE_SERVICE_ROLE_KEY=your-service-role-key

# URL del proyecto Supabase (para el cliente JS)
SUPABASE_URL=https://<project-ref>.supabase.co
```

- [ ] **Step 4: Verificar que los tests existentes siguen pasando**

```bash
cd dashboard && php artisan test --parallel 2>&1 | tail -5
```
Esperado: todos los tests anteriores pasan (la nueva conexión no afecta porque los modelos existentes usan sqlite).

- [ ] **Step 5: Commit**

```bash
git add dashboard/config/database.php dashboard/phpunit.xml dashboard/.env.example
git commit -m "feat(db): añadir conexión supabase con fallback a sqlite en tests"
```

---

### Task 2: Migraciones del equipo (Supabase)

**Files:**
- Create: `dashboard/database/migrations/team/2026_06_25_000001_create_team_projects_table.php`
- Create: `dashboard/database/migrations/team/2026_06_25_000002_create_team_tasks_table.php`
- Create: `dashboard/database/migrations/team/2026_06_25_000003_create_team_task_labels_table.php`
- Create: `dashboard/database/migrations/team/2026_06_25_000004_create_team_task_label_task_table.php`
- Create: `dashboard/database/migrations/team/2026_06_25_000005_create_team_task_checkboxes_table.php`
- Create: `dashboard/database/migrations/team/2026_06_25_000006_create_team_task_comments_table.php`
- Create: `dashboard/database/migrations/team/2026_06_25_000007_create_team_members_table.php`
- Create: `dashboard/database/migrations/team/2026_06_25_000008_add_assignee_to_team_tasks.php`

**Interfaces:**
- Produces: tablas `projects`, `tasks`, `task_labels`, `task_label_task`, `task_checkboxes`, `task_comments`, `team_members` en la conexión `supabase`. La tabla `tasks` incluye `assignee_id`.

- [ ] **Step 1: Crear migración de proyectos del equipo**

```php
// dashboard/database/migrations/team/2026_06_25_000001_create_team_projects_table.php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'supabase';

    public function up(): void
    {
        Schema::connection('supabase')->create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('color', 20)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('supabase')->dropIfExists('projects');
    }
};
```

- [ ] **Step 2: Crear migración de tareas del equipo**

```php
// dashboard/database/migrations/team/2026_06_25_000002_create_team_tasks_table.php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'supabase';

    public function up(): void
    {
        Schema::connection('supabase')->create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('todo');
            $table->string('priority')->nullable();
            $table->date('due_date')->nullable();
            $table->integer('position')->default(0);
            $table->dateTime('completed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['status', 'position']);
        });
    }

    public function down(): void
    {
        Schema::connection('supabase')->dropIfExists('tasks');
    }
};
```

- [ ] **Step 3: Crear migraciones de etiquetas, pivote, subtareas y comentarios**

```php
// dashboard/database/migrations/team/2026_06_25_000003_create_team_task_labels_table.php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'supabase';

    public function up(): void
    {
        Schema::connection('supabase')->create('task_labels', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('color', 20)->default('#64748b');
            $table->integer('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('supabase')->dropIfExists('task_labels');
    }
};
```

```php
// dashboard/database/migrations/team/2026_06_25_000004_create_team_task_label_task_table.php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'supabase';

    public function up(): void
    {
        Schema::connection('supabase')->create('task_label_task', function (Blueprint $table) {
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('label_id')->constrained('task_labels')->cascadeOnDelete();
            $table->primary(['task_id', 'label_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('supabase')->dropIfExists('task_label_task');
    }
};
```

```php
// dashboard/database/migrations/team/2026_06_25_000005_create_team_task_checkboxes_table.php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'supabase';

    public function up(): void
    {
        Schema::connection('supabase')->create('task_checkboxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('title');
            $table->boolean('checked')->default(false);
            $table->integer('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('supabase')->dropIfExists('task_checkboxes');
    }
};
```

```php
// dashboard/database/migrations/team/2026_06_25_000006_create_team_task_comments_table.php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'supabase';

    public function up(): void
    {
        Schema::connection('supabase')->create('task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->text('body');
            $table->string('author_name')->nullable();
            $table->string('author_token')->nullable();
            $table->timestamps();
            $table->index('author_token');
        });
    }

    public function down(): void
    {
        Schema::connection('supabase')->dropIfExists('task_comments');
    }
};
```

- [ ] **Step 4: Crear migración de `team_members`**

```php
// dashboard/database/migrations/team/2026_06_25_000007_create_team_members_table.php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'supabase';

    public function up(): void
    {
        Schema::connection('supabase')->create('team_members', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color', 20)->default('#64748b');
            $table->integer('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('supabase')->dropIfExists('team_members');
    }
};
```

- [ ] **Step 5: Crear migración de `assignee_id` en tasks del equipo**

```php
// dashboard/database/migrations/team/2026_06_25_000008_add_assignee_to_team_tasks.php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'supabase';

    public function up(): void
    {
        Schema::connection('supabase')->table('tasks', function (Blueprint $table) {
            $table->foreignId('assignee_id')
                ->nullable()
                ->after('project_id')
                ->constrained('team_members')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('supabase')->table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assignee_id');
        });
    }
};
```

- [ ] **Step 6: Ejecutar las migraciones del equipo en la conexión `supabase`**

En local, primero configura las vars de Supabase en `.env`. Luego:

```bash
cd dashboard
php artisan migrate --database=supabase --path=database/migrations/team
```

Esperado: `8 migrations run successfully.`

En tests, el comando es igual pero usa SQLite `:memory:` automáticamente por las env vars de `phpunit.xml`.

- [ ] **Step 7: Commit**

```bash
git add dashboard/database/migrations/team/
git commit -m "feat(db): añadir migraciones del equipo para conexión supabase"
```

---

### Task 3: Modelos del equipo

**Files:**
- Create: `dashboard/app/Models/TeamModel.php`
- Create: `dashboard/app/Models/TeamTask.php`
- Create: `dashboard/app/Models/TeamProject.php`
- Create: `dashboard/app/Models/TeamMember.php`
- Create: `dashboard/app/Models/TeamTaskLabel.php`
- Create: `dashboard/app/Models/TeamTaskCheckbox.php`
- Create: `dashboard/app/Models/TeamTaskComment.php`

**Interfaces:**
- Consumes: conexión `'supabase'` (Task 1), tablas en Supabase (Task 2).
- Produces: modelos usables como `TeamTask::with(['project','labels','checkboxes','comments','assignee'])->get()`.

- [ ] **Step 1: Crear `TeamModel` base**

```php
// dashboard/app/Models/TeamModel.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo base para entidades del equipo. Usa la conexión 'supabase'.
 */
abstract class TeamModel extends Model
{
    protected $connection = 'supabase';
}
```

- [ ] **Step 2: Crear `TeamMember`**

```php
// dashboard/app/Models/TeamMember.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class TeamMember extends TeamModel
{
    protected $fillable = ['name', 'color', 'position'];

    public function tasks(): HasMany
    {
        return $this->hasMany(TeamTask::class, 'assignee_id');
    }

    /** Iniciales para el avatar (hasta 2 letras). */
    public function initials(): string
    {
        $parts = explode(' ', trim($this->name));
        return strtoupper(
            count($parts) >= 2
                ? $parts[0][0] . $parts[1][0]
                : ($parts[0][0] ?? '?')
        );
    }
}
```

- [ ] **Step 3: Crear `TeamProject`**

```php
// dashboard/app/Models/TeamProject.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class TeamProject extends TeamModel
{
    protected $fillable = ['code', 'name', 'color', 'description'];

    public function tasks(): HasMany
    {
        return $this->hasMany(TeamTask::class, 'project_id');
    }
}
```

- [ ] **Step 4: Crear modelos auxiliares**

```php
// dashboard/app/Models/TeamTaskLabel.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TeamTaskLabel extends TeamModel
{
    protected $table    = 'task_labels';
    protected $fillable = ['title', 'color', 'position'];

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(TeamTask::class, 'task_label_task', 'label_id', 'task_id');
    }
}
```

```php
// dashboard/app/Models/TeamTaskCheckbox.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamTaskCheckbox extends TeamModel
{
    protected $table    = 'task_checkboxes';
    protected $fillable = ['task_id', 'title', 'checked', 'position'];
    protected $casts    = ['checked' => 'boolean', 'task_id' => 'integer'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(TeamTask::class, 'task_id');
    }
}
```

```php
// dashboard/app/Models/TeamTaskComment.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamTaskComment extends TeamModel
{
    protected $table    = 'task_comments';
    protected $fillable = ['task_id', 'body', 'author_name', 'author_token'];
    protected $casts    = ['task_id' => 'integer'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(TeamTask::class, 'task_id');
    }
}
```

- [ ] **Step 5: Crear `TeamTask`**

```php
// dashboard/app/Models/TeamTask.php
<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeamTask extends TeamModel
{
    use SoftDeletes;

    protected $table    = 'tasks';
    protected $fillable = [
        'project_id', 'assignee_id', 'title', 'description',
        'status', 'priority', 'due_date', 'position', 'completed_at',
    ];
    protected $attributes = ['status' => 'todo', 'position' => 0];
    protected $casts = [
        'project_id'   => 'integer',
        'assignee_id'  => 'integer',
        'status'       => TaskStatus::class,
        'priority'     => TaskPriority::class,
        'due_date'     => 'date',
        'position'     => 'integer',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (TeamTask $task) {
            if ($task->status === TaskStatus::Done) {
                $task->completed_at ??= now();
            } else {
                $task->completed_at = null;
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(TeamProject::class, 'project_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'assignee_id');
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(TeamTaskLabel::class, 'task_label_task', 'task_id', 'label_id')
            ->orderBy('task_labels.position')
            ->orderBy('task_labels.title');
    }

    public function checkboxes(): HasMany
    {
        return $this->hasMany(TeamTaskCheckbox::class, 'task_id')
            ->orderBy('position')->orderBy('id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TeamTaskComment::class, 'task_id')->orderBy('created_at');
    }
}
```

- [ ] **Step 6: Verificar que los tests existentes siguen pasando**

```bash
cd dashboard && php artisan test --parallel 2>&1 | tail -5
```
Esperado: sin regresiones.

- [ ] **Step 7: Commit**

```bash
git add dashboard/app/Models/Team*.php
git commit -m "feat(models): añadir TeamModel base y modelos del equipo (supabase)"
```

---

### Task 4: Controladores y rutas del equipo

**Files:**
- Create: `dashboard/app/Http/Controllers/TeamTaskController.php`
- Create: `dashboard/app/Http/Controllers/TeamMemberController.php`
- Modify: `dashboard/routes/web.php`
- Create: `dashboard/tests/Feature/TeamTaskControllerTest.php`
- Create: `dashboard/tests/Feature/TeamMemberControllerTest.php`

**Interfaces:**
- Consumes: `TeamTask`, `TeamProject`, `TeamMember`, `TeamTaskLabel` (Task 3).
- Produces:
  - `GET  /team/tasks`                → `TeamTaskController@index`
  - `POST /team/tasks`                → `TeamTaskController@store`
  - `PATCH /team/tasks/{task}`        → `TeamTaskController@update`
  - `PATCH /team/tasks/{task}/move`   → `TeamTaskController@move`
  - `DELETE /team/tasks/{task}`       → `TeamTaskController@destroy`
  - `GET  /team/tasks/peek`           → `TeamTaskController@peek`
  - `GET  /team/members`              → `TeamMemberController@index` (JSON)
  - `POST /team/members`              → `TeamMemberController@store`
  - `PATCH /team/members/{member}`    → `TeamMemberController@update`
  - `DELETE /team/members/{member}`   → `TeamMemberController@destroy`

- [ ] **Step 1: Escribir el test de `TeamTaskController`**

```php
// dashboard/tests/Feature/TeamTaskControllerTest.php
<?php

namespace Tests\Feature;

use App\Models\TeamTask;
use App\Models\TeamProject;
use App\Enums\TaskStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTaskControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ejecutar migraciones del equipo también en la conexión supabase (SQLite :memory: en tests)
        $this->artisan('migrate', ['--database' => 'supabase', '--path' => 'database/migrations/team']);
    }

    public function test_index_renders_team_board(): void
    {
        TeamTask::create(['title' => 'Tarea equipo', 'status' => 'todo', 'position' => 0]);

        $this->get('/team/tasks')->assertOk()->assertSee('Tarea equipo');
    }

    public function test_store_creates_team_task(): void
    {
        $this->post('/team/tasks', [
            '_token' => csrf_token(),
            'title'  => 'Nueva tarea',
            'status' => 'todo',
        ])->assertRedirect('/team/tasks');

        $this->assertDatabaseHas('tasks', ['title' => 'Nueva tarea'], 'supabase');
    }

    public function test_move_changes_status(): void
    {
        $task = TeamTask::create(['title' => 'T', 'status' => 'todo', 'position' => 0]);

        $this->patch("/team/tasks/{$task->id}/move", [
            '_token'   => csrf_token(),
            '_method'  => 'PATCH',
            'status'   => 'doing',
            'position' => 0,
        ])->assertJson(['ok' => true]);

        $this->assertEquals('doing', TeamTask::find($task->id)->status->value);
    }

    public function test_destroy_soft_deletes_task(): void
    {
        $task = TeamTask::create(['title' => 'T', 'status' => 'todo', 'position' => 0]);

        $this->delete("/team/tasks/{$task->id}")->assertRedirect('/team/tasks');

        $this->assertSoftDeleted('tasks', ['id' => $task->id], 'supabase');
    }
}
```

- [ ] **Step 2: Ejecutar el test para verificar que falla**

```bash
cd dashboard && php artisan test tests/Feature/TeamTaskControllerTest.php 2>&1 | tail -10
```
Esperado: FAIL — `TeamTaskController not found`.

- [ ] **Step 3: Crear `TeamTaskController`**

```php
// dashboard/app/Http/Controllers/TeamTaskController.php
<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\TeamMember;
use App\Models\TeamProject;
use App\Models\TeamTask;
use App\Models\TeamTaskLabel;
use App\Services\UserIdentity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeamTaskController extends Controller
{
    public function index(Request $request): View
    {
        $projectId  = $request->integer('project') ?: null;
        $assigneeId = $request->integer('assignee') ?: null;

        $tasks = TeamTask::with(['project', 'labels', 'checkboxes', 'comments', 'assignee'])
            ->when($projectId,  fn ($q) => $q->where('project_id', $projectId))
            ->when($assigneeId, fn ($q) => $q->where('assignee_id', $assigneeId))
            ->orderBy('position')
            ->get()
            ->groupBy(fn (TeamTask $t) => $t->status->value);

        return view('tasks.board', [
            'columns'    => TaskStatus::cases(),
            'priorities' => TaskPriority::cases(),
            'tasks'      => $tasks,
            'projects'   => TeamProject::orderBy('code')->get(),
            'labels'     => TeamTaskLabel::orderBy('position')->orderBy('title')->get(),
            'members'    => TeamMember::orderBy('position')->get(),
            'projectId'  => $projectId,
            'assigneeId' => $assigneeId,
            'priority'   => null,
            'mode'       => 'team',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateTask($request);
        $labelIds = $data['label_ids'] ?? [];
        unset($data['label_ids']);
        $data['position'] = (TeamTask::where('status', $data['status'])->max('position') ?? -1) + 1;

        $task = TeamTask::create($data);
        $task->labels()->sync($labelIds);

        return redirect()->route('team.tasks.index')->with('status', 'Tarea creada.');
    }

    public function update(Request $request, TeamTask $task): RedirectResponse
    {
        $data     = $this->validateTask($request);
        $labelIds = $data['label_ids'] ?? [];
        unset($data['label_ids']);

        $task->update($data);
        $task->labels()->sync($labelIds);

        return redirect()->route('team.tasks.index')->with('status', 'Tarea actualizada.');
    }

    public function destroy(TeamTask $task): RedirectResponse
    {
        $task->delete();

        return redirect()->route('team.tasks.index')->with('status', 'Tarea archivada.');
    }

    public function move(Request $request, TeamTask $task): JsonResponse
    {
        $data = $request->validate([
            'status'   => ['required', Rule::enum(TaskStatus::class)],
            'position' => ['required', 'integer', 'min:0'],
        ]);

        $oldStatus  = $task->status->value;
        $task->status = TaskStatus::from($data['status']);
        $task->save();

        $this->reindex($data['status'], $task->id, (int) $data['position']);
        if ($oldStatus !== $data['status']) {
            $this->reindex($oldStatus);
        }

        return response()->json(['ok' => true]);
    }

    public function peek(): JsonResponse
    {
        $latest = TeamTask::withTrashed()->max('updated_at');
        return response()->json(['latest' => $latest ? (string) $latest : null]);
    }

    private function reindex(string $status, ?int $insertId = null, int $insertAt = 0): void
    {
        $ids = TeamTask::where('status', $status)
            ->when($insertId !== null, fn ($q) => $q->where('id', '!=', $insertId))
            ->orderBy('position')->orderBy('id')
            ->pluck('id')->all();

        if ($insertId !== null) {
            array_splice($ids, max(0, min($insertAt, count($ids))), 0, [$insertId]);
        }

        foreach ($ids as $i => $id) {
            TeamTask::where('id', $id)->update(['position' => $i]);
        }
    }

    private function validateTask(Request $request): array
    {
        return $request->validate([
            'title'       => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'project_id'  => ['nullable', 'integer', 'exists:supabase.projects,id'],
            'assignee_id' => ['nullable', 'integer', 'exists:supabase.team_members,id'],
            'status'      => ['required', Rule::enum(TaskStatus::class)],
            'priority'    => ['nullable', Rule::enum(TaskPriority::class)],
            'due_date'    => ['nullable', 'date'],
            'label_ids'   => ['nullable', 'array'],
            'label_ids.*' => ['integer'],
        ]);
    }
}
```

- [ ] **Step 4: Crear `TeamMemberController`**

```php
// dashboard/app/Http/Controllers/TeamMemberController.php
<?php

namespace App\Http\Controllers;

use App\Models\TeamMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TeamMemberController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(TeamMember::orderBy('position')->get(['id', 'name', 'color']));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'  => ['required', 'string', 'max:80'],
            'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);
        $data['position'] = (TeamMember::max('position') ?? -1) + 1;
        TeamMember::create($data);

        return redirect()->route('settings.integrations')->with('status', 'Miembro añadido.');
    }

    public function update(Request $request, TeamMember $teamMember): RedirectResponse
    {
        $data = $request->validate([
            'name'  => ['required', 'string', 'max:80'],
            'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);
        $teamMember->update($data);

        return redirect()->route('settings.integrations')->with('status', 'Miembro actualizado.');
    }

    public function destroy(TeamMember $teamMember): RedirectResponse
    {
        $teamMember->delete();

        return redirect()->route('settings.integrations')->with('status', 'Miembro eliminado.');
    }
}
```

- [ ] **Step 5: Añadir rutas en `routes/web.php`**

Después del bloque de rutas de Tareas existente, añade:

```php
// ─────────────────── Equipo (Kanban compartido, Supabase) ───────────────────
Route::get('/team/tasks',                [TeamTaskController::class, 'index'])->name('team.tasks.index');
Route::get('/team/tasks/peek',           [TeamTaskController::class, 'peek'])->name('team.tasks.peek');
Route::post('/team/tasks',               [TeamTaskController::class, 'store'])->name('team.tasks.store');
Route::patch('/team/tasks/{task}',       [TeamTaskController::class, 'update'])->name('team.tasks.update');
Route::patch('/team/tasks/{task}/move',  [TeamTaskController::class, 'move'])->name('team.tasks.move');
Route::delete('/team/tasks/{task}',      [TeamTaskController::class, 'destroy'])->name('team.tasks.destroy');

Route::get('/team/members',              [TeamMemberController::class, 'index'])->name('team.members.index');
Route::post('/team/members',             [TeamMemberController::class, 'store'])->name('team.members.store');
Route::patch('/team/members/{teamMember}', [TeamMemberController::class, 'update'])->name('team.members.update');
Route::delete('/team/members/{teamMember}', [TeamMemberController::class, 'destroy'])->name('team.members.destroy');
```

Añade también los `use` statements al inicio del fichero:

```php
use App\Http\Controllers\TeamTaskController;
use App\Http\Controllers\TeamMemberController;
```

- [ ] **Step 6: Ejecutar tests y verificar que pasan**

```bash
cd dashboard && php artisan test tests/Feature/TeamTaskControllerTest.php tests/Feature/TeamMemberControllerTest.php 2>&1 | tail -10
```
Esperado: todos pasan.

- [ ] **Step 7: Verificar que los tests existentes no tienen regresiones**

```bash
cd dashboard && php artisan test --parallel 2>&1 | tail -5
```

- [ ] **Step 8: Commit**

```bash
git add dashboard/app/Http/Controllers/TeamTaskController.php \
        dashboard/app/Http/Controllers/TeamMemberController.php \
        dashboard/routes/web.php \
        dashboard/tests/Feature/TeamTaskControllerTest.php \
        dashboard/tests/Feature/TeamMemberControllerTest.php
git commit -m "feat(kanban): añadir controladores y rutas del equipo (supabase)"
```

---

### Task 5: Toggle Personal / Equipo en el board

**Files:**
- Modify: `dashboard/resources/views/tasks/board.blade.php`
- Modify: `dashboard/resources/js/kanban.js`

**Interfaces:**
- Consumes: rutas `team.tasks.index` y `tasks.index` (Tasks 1-4). Variable `$mode` (`'personal'|'team'`) pasada por los controladores. Variable `$members` (colección de TeamMember, solo en modo team).
- Produces: toggle visible en el header del board que navega entre `/tasks` y `/team/tasks`. En modo equipo, el board tiene `data-mode="team"` y el JS usa endpoints `/team/tasks/*`.

- [ ] **Step 1: Añadir `$mode` al `TaskController` existente (personal)**

En `app/Http/Controllers/TaskController.php`, en el método `index`, añade `'mode' => 'personal'` al array que se pasa a la vista:

```php
return view('tasks.board', [
    'columns'    => TaskStatus::cases(),
    'priorities' => TaskPriority::cases(),
    'tasks'      => $tasks,
    'projects'   => Project::orderBy('code')->get(),
    'labels'     => TaskLabel::orderBy('position')->orderBy('title')->get(),
    'projectId'  => $projectId,
    'priority'   => $priority,
    'mode'       => 'personal',       // ← añadir
    'members'    => collect(),        // ← añadir (vacío en personal)
    'assigneeId' => null,             // ← añadir
]);
```

- [ ] **Step 2: Añadir el toggle en `resources/views/tasks/board.blade.php`**

Localiza la línea que contiene `<h1 class="text-xl font-semibold tracking-tight">Tareas</h1>` y reemplaza el bloque `<div class="flex items-center gap-3">` que la contiene por:

```blade
<div class="flex items-center gap-3">
    <h1 class="text-xl font-semibold tracking-tight">Tareas</h1>
    <a href="{{ route('tasks.archived') }}" class="text-xs text-faint hover:underline">
        Archivadas
    </a>
    @if(config('database.connections.supabase.host'))
    <div class="flex items-center gap-1 bg-surface-2 rounded-lg p-0.5 text-sm">
        <a href="{{ route('tasks.index') }}"
           class="px-3 py-1 rounded-md transition-colors {{ $mode === 'personal' ? 'bg-surface-1 shadow-sm font-medium' : 'text-faint hover:text-default' }}">
            Personal
        </a>
        <a href="{{ route('team.tasks.index') }}"
           class="px-3 py-1 rounded-md transition-colors {{ $mode === 'team' ? 'bg-surface-1 shadow-sm font-medium' : 'text-faint hover:text-default' }}">
            Equipo
        </a>
    </div>
    @endif
</div>
```

- [ ] **Step 3: Pasar `mode` y URLs al JS desde la vista**

Al final del `@section('content')` en `board.blade.php`, antes del cierre `@endsection`, añade:

```blade
<script>
window.KANBAN_MODE = '{{ $mode }}';
window.KANBAN_ROUTES = {
    store:  '{{ $mode === "team" ? route("team.tasks.store")         : route("tasks.store") }}',
    move:   '{{ $mode === "team" ? "/team/tasks"                      : "/tasks" }}',
    update: '{{ $mode === "team" ? "/team/tasks"                      : "/tasks" }}',
    peek:   '{{ $mode === "team" ? route("team.tasks.peek")           : route("tasks.peek") }}',
    checkboxStore:  '{{ $mode === "team" ? "/team/tasks"  : "/tasks" }}',
    commentStore:   '{{ $mode === "team" ? "/team/tasks"  : "/tasks" }}',
};
@if($mode === 'team')
window.SUPABASE_URL  = '{{ env("SUPABASE_URL") }}';
window.SUPABASE_ANON_KEY = '{{ env("SUPABASE_ANON_KEY") }}';
@endif
</script>
```

- [ ] **Step 4: Actualizar `kanban.js` para usar `window.KANBAN_ROUTES`**

En `kanban.js`, busca la función `send` y el uso hardcodeado de rutas. Las referencias a `/tasks/` y URLs hardcodeadas deben leer de `window.KANBAN_ROUTES`. En la función `move` del drag & drop (busca `tasks/{id}/move`), cambia la URL a:

```javascript
const moveUrl = `${window.KANBAN_ROUTES.move}/${taskId}/move`;
```

En `store` (creación nueva tarea), el `action` del form ya está en el HTML del inline-add. Para el modal, busca el form `#task-new` y asegúrate de que el `action` use la ruta correcta — ya viene del Blade así que no necesita cambio en JS.

Para el polling (`initLivePolling`), cambia la URL de `peek` a:

```javascript
const url = window.KANBAN_ROUTES.peek;
```

- [ ] **Step 5: Verificar visualmente el toggle**

```bash
cd dashboard && php artisan serve --port=8100
```

Abre `http://localhost:8100/tasks`. Comprueba:
- Si `SUPABASE_DB_HOST` está en `.env`: aparece el toggle Personal/Equipo.
- Si no está: el toggle no aparece (solo el link "Archivadas").
- Clic en "Equipo" navega a `/team/tasks` sin errores.

- [ ] **Step 6: Commit**

```bash
git add dashboard/resources/views/tasks/board.blade.php \
        dashboard/resources/js/kanban.js \
        dashboard/app/Http/Controllers/TaskController.php
git commit -m "feat(kanban): añadir toggle Personal/Equipo en el header del board"
```

---

### Task 6: Assignee — miembro asignado en tarjetas del equipo

**Files:**
- Modify: `dashboard/resources/views/tasks/board.blade.php` (form-fields y card)
- Modify: `dashboard/resources/views/tasks/partials/form-fields.blade.php`
- Modify: `dashboard/resources/views/tasks/partials/card.blade.php`
- Modify: `dashboard/resources/js/kanban.js`

**Interfaces:**
- Consumes: `$members` (colección TeamMember), `$mode`, `$assigneeId` pasados por `TeamTaskController@index`.
- Produces: selector de asignado en el modal de edición (solo visible en modo equipo). Avatar con iniciales en la card. Filtro por asignado en el header del board.

- [ ] **Step 1: Añadir el selector de asignado en `form-fields.blade.php`**

En `resources/views/tasks/partials/form-fields.blade.php`, añade tras el selector de proyecto:

```blade
@if(isset($mode) && $mode === 'team' && isset($members) && $members->isNotEmpty())
<div>
    <label class="label" for="field-assignee">Asignado a</label>
    <select name="assignee_id" id="field-assignee" class="select">
        <option value="">Sin asignar</option>
        @foreach($members as $member)
            <option value="{{ $member->id }}"
                @selected((isset($task) && $task->assignee_id === $member->id)
                           || (old('assignee_id') == $member->id))>
                {{ $member->name }}
            </option>
        @endforeach
    </select>
</div>
@endif
```

- [ ] **Step 2: Añadir el avatar del asignado en `card.blade.php`**

En `resources/views/tasks/partials/card.blade.php`, localiza el final de la card (antes del cierre del elemento raíz) y añade:

```blade
@if(isset($task->assignee) && $task->assignee)
<div class="absolute bottom-2 right-2 w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold text-white select-none"
     style="background-color: {{ $task->assignee->color }}"
     title="{{ $task->assignee->name }}">
    {{ $task->assignee->initials() }}
</div>
@endif
```

- [ ] **Step 3: Añadir filtro por asignado en el header del board**

En `board.blade.php`, dentro del bloque `<form method="GET">` de filtros (donde están los selects de proyecto y prioridad), añade tras el select de prioridad:

```blade
@if(isset($mode) && $mode === 'team' && isset($members) && $members->isNotEmpty())
<div class="w-44">
    <select name="assignee" class="select text-sm" onchange="this.form.submit()">
        <option value="">Todo el equipo</option>
        @foreach($members as $member)
            <option value="{{ $member->id }}" @selected(isset($assigneeId) && $assigneeId === $member->id)>
                {{ $member->name }}
            </option>
        @endforeach
    </select>
</div>
@endif
```

También actualiza el `action` del form en modo equipo:

```blade
<form method="GET" action="{{ $mode === 'team' ? route('team.tasks.index') : route('tasks.index') }}" class="flex gap-2">
```

- [ ] **Step 4: Verificar visualmente**

Con el servidor corriendo (`php artisan serve --port=8100`), abre `/team/tasks`. Comprueba:
- El modal de nueva/editar tarea muestra el select "Asignado a".
- Al asignar un miembro y guardar, la card muestra el avatar con las iniciales.
- El filtro de asignado aparece en el header.

- [ ] **Step 5: Commit**

```bash
git add dashboard/resources/views/tasks/partials/ \
        dashboard/resources/views/tasks/board.blade.php
git commit -m "feat(kanban): añadir assignee en tarjetas del equipo con avatar y filtro"
```

---

### Task 7: Supabase Realtime

**Files:**
- Modify: `dashboard/package.json`
- Create: `dashboard/resources/js/kanban-team-realtime.js`
- Modify: `dashboard/resources/js/app.js`
- Modify: `dashboard/resources/views/tasks/board.blade.php`

**Interfaces:**
- Consumes: `window.SUPABASE_URL`, `window.SUPABASE_ANON_KEY`, `window.KANBAN_MODE` (Task 5). DOM del board con `data-task-list` y `data-task-board`.
- Produces: suscripción a cambios en la tabla `tasks` de Supabase. Cuando otro cliente mueve/crea/borra una tarea en modo equipo, el board se recarga automáticamente.

- [ ] **Step 1: Instalar `@supabase/supabase-js`**

```bash
cd dashboard && npm install @supabase/supabase-js
```

Verificar que se añadió en `package.json`:

```bash
grep supabase dashboard/package.json
```
Esperado: `"@supabase/supabase-js": "^2.x.x"`

- [ ] **Step 2: Crear `kanban-team-realtime.js`**

```javascript
// dashboard/resources/js/kanban-team-realtime.js
/**
 * Supabase Realtime para el Kanban del equipo.
 * Se suscribe a cambios en la tabla `tasks` y recarga el board
 * cuando otro cliente modifica algo. No actúa sobre cambios
 * propios (marcados por window.__taskMutationAt).
 */
import { createClient } from '@supabase/supabase-js';

let channel = null;

export function initTeamRealtime() {
    if (window.KANBAN_MODE !== 'team') return;
    if (!window.SUPABASE_URL || !window.SUPABASE_ANON_KEY) return;

    const supabase = createClient(window.SUPABASE_URL, window.SUPABASE_ANON_KEY);

    channel = supabase
        .channel('kanban-team')
        .on(
            'postgres_changes',
            { event: '*', schema: 'public', table: 'tasks' },
            (payload) => {
                // Ignorar cambios propios (hechos hace menos de 2 s)
                const ownMutation = window.__taskMutationAt
                    && (Date.now() - window.__taskMutationAt) < 2000;
                if (ownMutation) return;

                // Recarga silenciosa: un reload completo es simple y fiable
                // para sincronizar el estado completo del board.
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

- [ ] **Step 3: Importar e inicializar en `app.js`**

En `resources/js/app.js`, añade al final:

```javascript
import { initTeamRealtime } from './kanban-team-realtime.js';

document.addEventListener('DOMContentLoaded', () => {
    initTeamRealtime();
});
```

- [ ] **Step 4: Habilitar Realtime en Supabase Dashboard**

En el panel de Supabase:
1. Ve a **Database → Replication**.
2. Activa Realtime para la tabla `tasks` (toggle "Source").
3. Verifica que el estado es "Enabled".

> Este paso es manual (UI de Supabase) y solo se hace una vez al crear el proyecto.

- [ ] **Step 5: Rebuild de assets**

```bash
cd dashboard && npm run build
```
Esperado: sin errores. El bundle incluye `@supabase/supabase-js`.

- [ ] **Step 6: Verificar en dos ventanas**

Con el servidor corriendo y Supabase configurado en `.env`:
1. Abre `/team/tasks` en dos ventanas del browser.
2. En la ventana A, mueve una tarjeta a otra columna.
3. Comprueba que la ventana B se actualiza (reload automático) en menos de 3 segundos.

- [ ] **Step 7: Commit**

```bash
git add dashboard/package.json dashboard/package-lock.json \
        dashboard/resources/js/kanban-team-realtime.js \
        dashboard/resources/js/app.js \
        dashboard/public/build/
git commit -m "feat(kanban): añadir Supabase Realtime para actualizaciones en tiempo real del equipo"
```

---

### Task 8: Settings — configuración de Supabase y Base44

**Files:**
- Modify: `dashboard/app/Http/Controllers/SettingsController.php`
- Create: `dashboard/resources/views/settings/integrations.blade.php`
- Modify: `dashboard/resources/views/layouts/settings.blade.php`
- Modify: `dashboard/routes/web.php`

**Interfaces:**
- Consumes: `Setting::get/set` (modelo existente). `TeamMember` (Task 3). Rutas `team.members.*` (Task 4).
- Produces:
  - `GET/POST /settings/integrations` → sección con estado conexión Supabase + CRUD team_members + config Base44.

- [ ] **Step 1: Añadir rutas de integrations en `routes/web.php`**

```php
Route::get('/settings/integrations',  [\App\Http\Controllers\SettingsController::class, 'integrations'])->name('settings.integrations');
Route::post('/settings/integrations', [\App\Http\Controllers\SettingsController::class, 'saveIntegrations'])->name('settings.integrations.save');
```

- [ ] **Step 2: Añadir métodos al `SettingsController`**

```php
public function integrations(): View
{
    $supConnected = (bool) config('database.connections.supabase.host');
    $members      = $supConnected ? \App\Models\TeamMember::orderBy('position')->get() : collect();

    return view('settings.integrations', [
        'supConnected' => $supConnected,
        'base44Url'    => Setting::get('base44.url', ''),
        'base44Token'  => Setting::get('base44.token', '') ? '••••••••' : '',
        'members'      => $members,
    ]);
}

public function saveIntegrations(Request $request): RedirectResponse
{
    $data = $request->validate([
        'base44_url'   => ['nullable', 'url', 'max:255'],
        'base44_token' => ['nullable', 'string', 'max:500'],
    ]);

    if ($data['base44_url'] !== null) {
        Setting::set('base44.url', $data['base44_url']);
    }
    // Solo actualizar el token si no es la máscara
    if ($data['base44_token'] && $data['base44_token'] !== '••••••••') {
        Setting::set('base44.token', $data['base44_token']);
    }

    return redirect()->route('settings.integrations')->with('status', 'Ajustes de integración guardados.');
}
```

- [ ] **Step 3: Crear la vista `settings/integrations.blade.php`**

```blade
{{-- dashboard/resources/views/settings/integrations.blade.php --}}
@extends('layouts.settings')
@section('title', 'Integraciones')
@section('settings-content')

<div class="space-y-8">

    {{-- ── Supabase (Kanban de equipo) ── --}}
    <section>
        <h2 class="text-base font-semibold mb-1">Kanban de equipo (Supabase)</h2>
        <p class="text-sm text-faint mb-4">
            La conexión se configura en el fichero <code class="code">.env</code> del servidor
            con las variables <code class="code">SUPABASE_DB_*</code>.
        </p>

        <div class="flex items-center gap-2 mb-6">
            @if($supConnected)
                <span class="inline-flex items-center gap-1.5 text-sm text-emerald-600 dark:text-emerald-400">
                    <x-icon name="check" class="w-4 h-4" /> Conectado
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 text-sm text-amber-600 dark:text-amber-400">
                    <x-icon name="warning" class="w-4 h-4" /> Sin configurar
                </span>
                <span class="text-xs text-faint">Añade <code class="code">SUPABASE_DB_HOST</code> al .env para activar el Kanban de equipo.</span>
            @endif
        </div>

        @if($supConnected)
        {{-- CRUD de miembros del equipo --}}
        <h3 class="text-sm font-semibold mb-3">Miembros del equipo</h3>
        @if($members->isEmpty())
            <p class="text-sm text-faint mb-3">No hay miembros. Añade el primero:</p>
        @else
            <ul class="space-y-2 mb-4">
                @foreach($members as $member)
                <li class="flex items-center gap-3">
                    <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold text-white"
                          style="background-color: {{ $member->color }}">
                        {{ $member->initials() }}
                    </span>
                    <span class="flex-1 text-sm">{{ $member->name }}</span>
                    <form method="POST" action="{{ route('team.members.destroy', $member) }}">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn-ghost text-xs text-rose-500">Eliminar</button>
                    </form>
                </li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('team.members.store') }}" class="flex items-end gap-3">
            @csrf
            <div class="flex-1">
                <label class="label">Nombre</label>
                <input type="text" name="name" required maxlength="80" class="input" placeholder="Ana García">
            </div>
            <div>
                <label class="label">Color</label>
                <input type="color" name="color" value="#6366f1" class="h-9 w-12 rounded border border-default cursor-pointer">
            </div>
            <button type="submit" class="btn">Añadir</button>
        </form>
        @endif
    </section>

    <hr class="divider">

    {{-- ── Base44 CRM ── --}}
    <section>
        <h2 class="text-base font-semibold mb-1">CRM Base44</h2>
        <p class="text-sm text-faint mb-4">
            URL y token de la API REST del CRM. Cuando esté disponible, el comando
            <code class="code">php artisan crm:sync</code> importará proyectos y tareas.
        </p>

        <form method="POST" action="{{ route('settings.integrations.save') }}" class="space-y-4 max-w-md">
            @csrf
            <div>
                <label class="label" for="base44-url">URL de la API</label>
                <input type="url" id="base44-url" name="base44_url" maxlength="255"
                       value="{{ old('base44_url', $base44Url) }}"
                       class="input" placeholder="https://tu-app.base44.app/api">
            </div>
            <div>
                <label class="label" for="base44-token">Token (Bearer)</label>
                <input type="password" id="base44-token" name="base44_token" maxlength="500"
                       value="{{ old('base44_token', $base44Token) }}"
                       class="input" placeholder="Deja en blanco para no cambiar">
            </div>
            <button type="submit" class="btn">Guardar</button>
        </form>
    </section>
</div>
@endsection
```

- [ ] **Step 4: Añadir el enlace "Integraciones" en el sidebar de settings**

En `resources/views/layouts/settings.blade.php`, localiza la lista de enlaces del mini-sidebar y añade:

```blade
<a href="{{ route('settings.integrations') }}"
   class="settings-nav__link {{ request()->routeIs('settings.integrations') ? 'settings-nav__link--active' : '' }}">
    Integraciones
</a>
```

- [ ] **Step 5: Verificar en el browser**

```bash
cd dashboard && php artisan serve --port=8100
```

Abre `http://localhost:8100/settings/integrations`. Comprueba:
- La sección Supabase muestra "Sin configurar" si no hay `SUPABASE_DB_HOST` en `.env`.
- Si hay conexión, muestra el formulario de miembros.
- La sección Base44 permite guardar URL + token.

- [ ] **Step 6: Commit**

```bash
git add dashboard/app/Http/Controllers/SettingsController.php \
        dashboard/resources/views/settings/integrations.blade.php \
        dashboard/resources/views/layouts/settings.blade.php \
        dashboard/routes/web.php
git commit -m "feat(settings): añadir sección Integraciones con config Supabase y Base44"
```

---

### Task 9: Render deployment + GitHub Actions keep-alive

**Files:**
- Create: `render.yaml`
- Create: `.github/workflows/keep-alive.yml`
- Modify: `dashboard/.env.example` (añadir `APP_MODE`)

**Interfaces:**
- Consumes: repo GitHub con el código. Cuenta Render (free tier). Variables de entorno configuradas en Render dashboard.
- Produces: app desplegada en Render accesible via URL pública. Cron que hace ping cada 10 min en horario laboral para evitar que Render duerma.

- [ ] **Step 1: Crear `render.yaml` en la raíz del repo**

```yaml
# render.yaml
services:
  - type: web
    name: trackactivity-team
    env: php
    rootDir: dashboard
    buildCommand: >
      composer install --no-dev --optimize-autoloader &&
      npm ci && npm run build &&
      php artisan config:cache &&
      php artisan route:cache &&
      php artisan view:cache &&
      php artisan migrate --database=supabase --path=database/migrations/team --force
    startCommand: php artisan serve --host=0.0.0.0 --port=$PORT
    envVars:
      - key: APP_ENV
        value: production
      - key: APP_DEBUG
        value: false
      - key: APP_KEY
        generateValue: true
      - key: APP_TIMEZONE
        value: UTC
      - key: DB_CONNECTION
        value: sqlite
      - key: DB_DATABASE
        value: /tmp/trackactivity-render.sqlite
      - key: SESSION_DRIVER
        value: file
      - key: CACHE_STORE
        value: file
      - key: APP_MODE
        value: team_only
      # Las siguientes se rellenan manualmente en el dashboard de Render:
      - key: SUPABASE_DB_HOST
        sync: false
      - key: SUPABASE_DB_PORT
        value: 5432
      - key: SUPABASE_DB_DATABASE
        value: postgres
      - key: SUPABASE_DB_USERNAME
        value: postgres
      - key: SUPABASE_DB_PASSWORD
        sync: false
      - key: SUPABASE_DB_SSLMODE
        value: require
      - key: SUPABASE_URL
        sync: false
      - key: SUPABASE_ANON_KEY
        sync: false
      - key: SUPABASE_SERVICE_ROLE_KEY
        sync: false
```

- [ ] **Step 2: Ocultar el toggle "Personal" en modo `team_only`**

En `board.blade.php`, en el bloque del toggle, cambia la condición:

```blade
@if(config('database.connections.supabase.host') && env('APP_MODE') !== 'team_only')
```

Y el import del board en Render (modo `team_only`) debe redirigir `/tasks` → `/team/tasks`. Añade en `routes/web.php` al inicio de las rutas:

```php
// En Render (APP_MODE=team_only) redirigir la raíz del kanban al equipo
if (env('APP_MODE') === 'team_only') {
    Route::redirect('/tasks', '/team/tasks');
}
```

- [ ] **Step 3: Crear `.github/workflows/keep-alive.yml`**

```yaml
# .github/workflows/keep-alive.yml
name: Keep Render alive

on:
  schedule:
    # Cada 10 minutos de lunes a viernes, 7h-20h UTC
    - cron: '*/10 7-20 * * 1-5'
  workflow_dispatch:

jobs:
  ping:
    runs-on: ubuntu-latest
    steps:
      - name: Ping Render
        run: |
          curl -sf "${{ secrets.RENDER_APP_URL }}/team/tasks/peek" \
            -o /dev/null \
            -w "HTTP %{http_code}\n" \
            || echo "Ping failed (app may be waking up)"
```

> **Nota:** Añade el secret `RENDER_APP_URL` en GitHub → Settings → Secrets → Actions con el valor `https://trackactivity-team.onrender.com` (o la URL que Render asigne).

- [ ] **Step 4: Instrucciones de primer despliegue**

1. Haz push del repo a GitHub.
2. En Render: **New → Web Service** → conecta el repo → selecciona el `render.yaml` como configuración.
3. Rellena en Render Dashboard los secrets marcados como `sync: false` (contraseña Supabase, URLs, keys).
4. Pulsa **Deploy**. El build tarda ~3 min.
5. Una vez desplegado, añade el secret `RENDER_APP_URL` en GitHub con la URL de Render.
6. Verifica que el GitHub Actions cron aparece en la pestaña Actions del repo.

- [ ] **Step 5: Verificar el deployment**

Abre la URL de Render en el browser. Comprueba:
- La app carga el board del equipo directamente (sin toggle Personal).
- No hay errores 500 en los logs de Render.
- El endpoint `/team/tasks/peek` devuelve `{"latest": ...}`.

- [ ] **Step 6: Commit**

```bash
git add render.yaml .github/workflows/keep-alive.yml dashboard/.env.example \
        dashboard/routes/web.php dashboard/resources/views/tasks/board.blade.php
git commit -m "feat(deploy): añadir render.yaml y GitHub Actions keep-alive para Render"
```

---

## Auto-revisión del plan

### Cobertura del spec

| Requisito del spec | Task |
|---|---|
| Conexión `supabase` en Laravel | Task 1 |
| Migraciones equipo en Supabase | Task 2 |
| TeamModel base + modelos del equipo | Task 3 |
| TeamTaskController + rutas | Task 4 |
| TeamMemberController + rutas | Task 4 |
| Toggle Personal/Equipo en board | Task 5 |
| `$mode` + `window.KANBAN_ROUTES` | Task 5 |
| Assignee picker en modal | Task 6 |
| Avatar en card | Task 6 |
| Filtro por asignado | Task 6 |
| Supabase Realtime (JS) | Task 7 |
| Settings: Supabase status + team_members CRUD | Task 8 |
| Settings: Base44 URL + token | Task 8 |
| `render.yaml` con rootDir=dashboard | Task 9 |
| GitHub Actions keep-alive cada 10 min | Task 9 |
| `APP_MODE=team_only` en Render | Task 9 |

Todos los requisitos cubiertos. Sin gaps.

### Consistencia de tipos

- `TeamTask::$connection = 'supabase'` heredado de `TeamModel` — consistente en Tasks 3 y 4.
- `window.KANBAN_ROUTES` definido en Task 5, consumido en Task 5 (kanban.js). Consistente.
- `$mode` pasado como `'personal'`/`'team'` en Task 5, leído en Tasks 6, 8. Consistente.
- Rutas: `team.tasks.index`, `team.tasks.store`, `team.tasks.move`, `team.members.store` — definidas en Task 4, usadas en Tasks 5-8. Consistente.
