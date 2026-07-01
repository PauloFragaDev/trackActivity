# Team Kanban Public Deploy Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add real login to trackActivity and a global hardening mode
(`APP_MODE=team_only`) so `dashboard/` can be deployed a second time, on a
VPS the user controls, exposing only the team Kanban to the user, his
coworker, and his boss — reusing the existing codebase, Supabase data, and
Dockerfile instead of a new project.

**Architecture:** One Laravel codebase, two runtime configurations. Locally
(`APP_MODE` unset) nothing changes: no login, session-based identity picker
as today. On the VPS (`APP_MODE=team_only`), a new global middleware 404s
every route outside `/team/*`, `/login`, `/logout`, and the health check;
`auth` guards the `/team/*` group; logging in sets the same session keys
(`team_member_id`, `team_member_name`) the existing team controllers already
read, so no team controller changes are needed. Docker Compose + Caddy on
the VPS reuse the existing `dashboard/Dockerfile`.

**Tech Stack:** Laravel 11 · PHP 8.4 · SQLite (local `users` table) ·
Supabase PostgreSQL (team data, unchanged) · Docker Compose · Caddy
(automatic TLS) · PHPUnit

## Global Constraints

- Spec: `docs/superpowers/specs/2026-07-01-team-kanban-public-deploy-design.md`.
- App lives in `dashboard/`; all file paths below are relative to
  `dashboard/` unless explicitly prefixed with `../` (repo root).
- `users` table lives on the **default** connection (SQLite) — never
  Supabase. `users.team_member_id` has **no DB-level foreign key** (SQLite
  `users` cannot constrain against a Postgres table on a different
  connection); it is a plain nullable `unsignedBigInteger`, validated in
  application code only.
- In tests: Supabase-backed models need
  `$this->artisan('migrate', ['--database' => 'supabase', '--path' => 'database/migrations/team']);`
  in `setUp()` (see `tests/Feature/RestoreTeamIdentityTest.php` for the
  existing pattern) plus the `RefreshDatabase` trait.
- `env('APP_MODE')` (not `config(...)`) is the existing guard style already
  used in `routes/web.php:31` (`if (env('APP_MODE') === 'team_only')`) —
  keep using `env()` directly for consistency, not a new config key.
- Tests that toggle `APP_MODE` at runtime use `putenv('APP_MODE=team_only')`
  in the test and `putenv('APP_MODE')` (unset) in `tearDown()` — `env()`
  reads live process env, `putenv` is the only way to flip it mid-test.
- Login sets `session(['team_member_id' => ..., 'team_member_name' => ...])`
  — the exact same keys `TeamTaskController`, `TeamTaskCommentController`,
  `TeamTransferController`, `NotificationController`, `RestoreTeamIdentity`
  already read. **No existing controller changes.**
- Commits: Conventional Commits, no `Co-Authored-By`, no emojis.
- `.card`, `.input`, `.btn`, `.label` are existing Tailwind component classes
  in `resources/css/app.css` — reuse them, do not invent new ones for the
  login form.

---

## File Map

| Action | File |
|--------|------|
| Create | `database/migrations/2026_07_01_000020_create_users_table.php` |
| Create | `app/Models/User.php` |
| Create | `tests/Feature/UserModelTest.php` |
| Create | `app/Http/Controllers/Auth/LoginController.php` |
| Create | `resources/views/auth/login.blade.php` |
| Modify | `routes/web.php` |
| Create | `tests/Feature/Auth/LoginTest.php` |
| Create | `database/seeders/TeamUsersSeeder.php` |
| Modify | `database/seeders/DatabaseSeeder.php` |
| Modify | `.env.example` |
| Create | `tests/Feature/TeamUsersSeederTest.php` |
| Create | `app/Http/Middleware/RestrictToTeamOnly.php` |
| Modify | `bootstrap/app.php` |
| Modify | `routes/web.php` (second pass: root redirect + conditional `auth`) |
| Create | `tests/Feature/RestrictToTeamOnlyMiddlewareTest.php` |
| Modify | `resources/views/tasks/board.blade.php` |
| Modify | `resources/js/kanban.js` |
| Create | `../infra/docker-compose.yml` |
| Create | `../infra/Caddyfile` |
| Create | `../infra/deploy.sh` |
| Delete | `../render.yaml` |
| Delete | `../.github/workflows/keep-alive.yml` |

---

### Task 1: `users` table + `User` model

**Files:**
- Create: `database/migrations/2026_07_01_000020_create_users_table.php`
- Create: `app/Models/User.php`
- Create: `tests/Feature/UserModelTest.php`

**Interfaces:**
- Produces: `App\Models\User` (Eloquent, `Authenticatable`) with
  `$fillable = ['name', 'email', 'password', 'team_member_id']`, an
  auto-hashing `password` cast, and a `teamMember(): BelongsTo` relation to
  `App\Models\TeamMember` (Supabase connection, resolved via the relation's
  own model — no cross-connection FK needed for Eloquent to load it).

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/UserModelTest.php
<?php

namespace Tests\Feature;

use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--database' => 'supabase', '--path' => 'database/migrations/team']);
    }

    public function test_password_is_hashed_automatically(): void
    {
        $user = User::create([
            'name'     => 'Ana',
            'email'    => 'ana@example.com',
            'password' => 'secret123',
        ]);

        $this->assertTrue(Hash::check('secret123', $user->password));
    }

    public function test_team_member_relation_resolves_across_connections(): void
    {
        $member = TeamMember::create(['name' => 'Ana', 'color' => '#6366f1', 'position' => 0]);
        $user   = User::create([
            'name'           => 'Ana',
            'email'          => 'ana@example.com',
            'password'       => 'secret123',
            'team_member_id' => $member->id,
        ]);

        $this->assertEquals('Ana', $user->teamMember->name);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/UserModelTest.php -v`
Expected: FAIL — `Class "App\Models\User" not found`

- [ ] **Step 3: Create the migration**

```php
// database/migrations/2026_07_01_000020_create_users_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            // Sin FK: users vive en sqlite, team_members en la conexión
            // 'supabase' (Postgres) — conexiones distintas no admiten FK
            // cruzada. Validado en la capa de aplicación (seeder / login).
            $table->unsignedBigInteger('team_member_id')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
```

- [ ] **Step 4: Create the `User` model**

```php
// app/Models/User.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = ['name', 'email', 'password', 'team_member_id'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password'       => 'hashed',
            'team_member_id' => 'integer',
        ];
    }

    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'team_member_id');
    }
}
```

- [ ] **Step 5: Run migration + test to verify it passes**

```bash
php artisan migrate
php artisan test tests/Feature/UserModelTest.php -v
```
Expected: 2 tests pass.

- [ ] **Step 6: Run full suite for regressions**

```bash
php artisan test 2>&1 | tail -5
```
Expected: all previous tests still pass, plus the 2 new ones (280 total).

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_01_000020_create_users_table.php \
        app/Models/User.php \
        tests/Feature/UserModelTest.php
git commit -m "feat(auth): añadir tabla users y modelo User"
```

---

### Task 2: Login controller, routes, view — login sets team identity

**Files:**
- Create: `app/Http/Controllers/Auth/LoginController.php`
- Create: `resources/views/auth/login.blade.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/Auth/LoginTest.php`

**Interfaces:**
- Consumes: `App\Models\User` (Task 1), `App\Models\TeamMember` (existing).
- Produces:
  - `GET  /login`  → `LoginController@create`, named `login`
  - `POST /login`  → `LoginController@store`, named `login.store`
  - `POST /logout` → `LoginController@destroy`, named `logout`
  - On successful login: `Auth::user()` set, session has
    `team_member_id` (int) and `team_member_name` (string) populated from
    the user's linked `TeamMember` — same keys the rest of the app already
    reads.

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/Auth/LoginTest.php
<?php

namespace Tests\Feature\Auth;

use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--database' => 'supabase', '--path' => 'database/migrations/team']);
    }

    public function test_login_form_renders(): void
    {
        $this->get('/login')->assertOk()->assertSee('Entrar');
    }

    public function test_valid_credentials_log_in_and_set_team_identity(): void
    {
        $member = TeamMember::create(['name' => 'Ana', 'color' => '#6366f1', 'position' => 0]);
        $user   = User::create([
            'name'           => 'Ana',
            'email'          => 'ana@example.com',
            'password'       => 'secret123',
            'team_member_id' => $member->id,
        ]);

        $response = $this->post('/login', [
            'email'    => 'ana@example.com',
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('team.tasks.index'));
        $this->assertAuthenticatedAs($user);
        $this->assertEquals($member->id, session('team_member_id'));
        $this->assertEquals('Ana', session('team_member_name'));
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        User::create(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'secret123']);

        $this->post('/login', ['email' => 'ana@example.com', 'password' => 'wrong'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_logout_clears_session_and_identity(): void
    {
        $member = TeamMember::create(['name' => 'Ana', 'color' => '#6366f1', 'position' => 0]);
        $user   = User::create([
            'name' => 'Ana', 'email' => 'ana@example.com',
            'password' => 'secret123', 'team_member_id' => $member->id,
        ]);
        $this->actingAs($user);
        session(['team_member_id' => $member->id, 'team_member_name' => 'Ana']);

        $this->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertNull(session('team_member_id'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Auth/LoginTest.php -v`
Expected: FAIL — route `/login` not found (404).

- [ ] **Step 3: Create `LoginController`**

```php
// app/Http/Controllers/Auth/LoginController.php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('team.tasks.index');
        }

        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            return back()
                ->withErrors(['email' => 'Credenciales incorrectas.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        /** @var User $user */
        $user   = Auth::user();
        $member = $user->team_member_id ? TeamMember::find($user->team_member_id) : null;
        if ($member) {
            session([
                'team_member_id'   => $member->id,
                'team_member_name' => $member->name,
            ]);
        }

        return redirect()->intended(route('team.tasks.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->forget(['team_member_id', 'team_member_name']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
```

- [ ] **Step 4: Create the login view**

```blade
{{-- resources/views/auth/login.blade.php --}}
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>trackActivity · Equipo</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen flex items-center justify-center bg-surface-0 px-4">
    <form method="POST" action="{{ route('login.store') }}" class="card p-6 w-full max-w-sm space-y-4">
        @csrf
        <h1 class="text-lg font-semibold tracking-tight">Kanban de equipo</h1>

        @error('email')
            <p class="text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
        @enderror

        <div>
            <label class="label" for="email">Email</label>
            <input type="email" id="email" name="email" required autofocus
                   value="{{ old('email') }}" class="input">
        </div>
        <div>
            <label class="label" for="password">Contraseña</label>
            <input type="password" id="password" name="password" required class="input">
        </div>

        <button type="submit" class="btn w-full">Entrar</button>
    </form>
</body>
</html>
```

- [ ] **Step 5: Add routes**

In `routes/web.php`, add near the top (after the `use Illuminate\Support\Facades\Route;` line, before the `APP_MODE` redirect block):

```php
Route::get('/login',   [\App\Http\Controllers\Auth\LoginController::class, 'create'])->name('login');
Route::post('/login',  [\App\Http\Controllers\Auth\LoginController::class, 'store'])->name('login.store');
Route::post('/logout', [\App\Http\Controllers\Auth\LoginController::class, 'destroy'])->name('logout');
```

- [ ] **Step 6: Run test to verify it passes**

```bash
php artisan test tests/Feature/Auth/LoginTest.php -v
```
Expected: 4 tests pass.

- [ ] **Step 7: Run full suite for regressions**

```bash
php artisan test 2>&1 | tail -5
```
Expected: no regressions (284 total).

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Auth/LoginController.php \
        resources/views/auth/login.blade.php \
        routes/web.php \
        tests/Feature/Auth/LoginTest.php
git commit -m "feat(auth): añadir login/logout que fija la identidad de equipo en sesión"
```

---

### Task 3: `TeamUsersSeeder` — cuentas reproducibles desde variables de entorno

**Files:**
- Create: `database/seeders/TeamUsersSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Modify: `.env.example`
- Create: `tests/Feature/TeamUsersSeederTest.php`

**Interfaces:**
- Consumes: `App\Models\User` (Task 1). Reads `TEAM_USER_{1,2,3}_EMAIL`,
  `TEAM_USER_{1,2,3}_PASSWORD`, `TEAM_USER_{1,2,3}_NAME` (optional),
  `TEAM_USER_{1,2,3}_MEMBER_ID` (optional) from the environment.
- Produces: idempotent `User::updateOrCreate` per configured slot — no
  hardcoded credentials in the repo; re-running `php artisan db:seed` after
  a redeploy recreates the same 3 accounts without duplicating them.

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/TeamUsersSeederTest.php
<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\TeamUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamUsersSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        foreach (['EMAIL', 'PASSWORD', 'NAME', 'MEMBER_ID'] as $suffix) {
            putenv("TEAM_USER_1_{$suffix}");
        }
        parent::tearDown();
    }

    public function test_creates_user_from_env_vars(): void
    {
        putenv('TEAM_USER_1_EMAIL=ana@example.com');
        putenv('TEAM_USER_1_PASSWORD=secret123');
        putenv('TEAM_USER_1_NAME=Ana');
        putenv('TEAM_USER_1_MEMBER_ID=5');

        (new TeamUsersSeeder())->run();

        $this->assertDatabaseHas('users', [
            'email'          => 'ana@example.com',
            'name'           => 'Ana',
            'team_member_id' => 5,
        ]);
    }

    public function test_is_idempotent_when_run_twice(): void
    {
        putenv('TEAM_USER_1_EMAIL=ana@example.com');
        putenv('TEAM_USER_1_PASSWORD=secret123');

        (new TeamUsersSeeder())->run();
        (new TeamUsersSeeder())->run();

        $this->assertEquals(1, User::where('email', 'ana@example.com')->count());
    }

    public function test_skips_slots_without_email(): void
    {
        (new TeamUsersSeeder())->run();

        $this->assertEquals(0, User::count());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/TeamUsersSeederTest.php -v`
Expected: FAIL — `Class "Database\Seeders\TeamUsersSeeder" not found`

- [ ] **Step 3: Create the seeder**

```php
// database/seeders/TeamUsersSeeder.php
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Crea/actualiza las cuentas de login del Kanban público desde variables de
 * entorno — nunca hardcodeadas — para poder recrearlas en cada deploy sin
 * guardar contraseñas reales en el repo.
 */
class TeamUsersSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([1, 2, 3] as $n) {
            $email = env("TEAM_USER_{$n}_EMAIL");
            if (! $email) {
                continue;
            }

            User::updateOrCreate(
                ['email' => $email],
                [
                    'name'           => env("TEAM_USER_{$n}_NAME", $email),
                    'password'       => env("TEAM_USER_{$n}_PASSWORD"),
                    'team_member_id' => env("TEAM_USER_{$n}_MEMBER_ID") ?: null,
                ]
            );
        }
    }
}
```

- [ ] **Step 4: Register it in `DatabaseSeeder`**

In `database/seeders/DatabaseSeeder.php`, add `TeamUsersSeeder::class` to the
`$this->call([...])` array:

```php
public function run(): void
{
    $this->call([
        ScoringRulesSeeder::class,
        ProjectsSeeder::class,
        MappingsSeeder::class,
        TeamUsersSeeder::class,
    ]);
}
```

- [ ] **Step 5: Document the env vars**

Append to `.env.example` (after the existing Supabase block):

```
# ──────────────────────────────────────────────
# Cuentas de login del Kanban público (opcional — solo instancia team_only)
# Rellena hasta 3 slots. team_member_id = id del TeamMember en Supabase.
# Tras rellenarlos, corre: php artisan db:seed --class=TeamUsersSeeder
# ──────────────────────────────────────────────
TEAM_USER_1_EMAIL=
TEAM_USER_1_PASSWORD=
TEAM_USER_1_NAME=
TEAM_USER_1_MEMBER_ID=

TEAM_USER_2_EMAIL=
TEAM_USER_2_PASSWORD=
TEAM_USER_2_NAME=
TEAM_USER_2_MEMBER_ID=

TEAM_USER_3_EMAIL=
TEAM_USER_3_PASSWORD=
TEAM_USER_3_NAME=
TEAM_USER_3_MEMBER_ID=
```

- [ ] **Step 6: Run test to verify it passes**

```bash
php artisan test tests/Feature/TeamUsersSeederTest.php -v
```
Expected: 3 tests pass.

- [ ] **Step 7: Run full suite for regressions**

```bash
php artisan test 2>&1 | tail -5
```
Expected: no regressions (287 total).

- [ ] **Step 8: Commit**

```bash
git add database/seeders/TeamUsersSeeder.php \
        database/seeders/DatabaseSeeder.php \
        .env.example \
        tests/Feature/TeamUsersSeederTest.php
git commit -m "feat(auth): añadir seeder de cuentas de equipo desde variables de entorno"
```

---

### Task 4: Endurecer rutas en modo `team_only` + redirect de raíz + `auth` condicional

**Files:**
- Create: `app/Http/Middleware/RestrictToTeamOnly.php`
- Modify: `bootstrap/app.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/RestrictToTeamOnlyMiddlewareTest.php`

**Interfaces:**
- Consumes: `env('APP_MODE')` (existing pattern), named route `login` (Task 2).
- Produces: when `APP_MODE=team_only`, any request whose path is not `/`,
  `team`, `team/*`, `login`, `logout`, or `up` gets a 404. The `/team/*`
  route group additionally requires `auth` in that mode (redirects
  unauthenticated visitors to `/login` — Laravel's default `Authenticate`
  middleware behavior when a route named `login` exists).

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/RestrictToTeamOnlyMiddlewareTest.php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestrictToTeamOnlyMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        putenv('APP_MODE');
        parent::tearDown();
    }

    public function test_non_team_routes_pass_through_when_app_mode_is_not_team_only(): void
    {
        $this->get('/settings')->assertOk();
    }

    public function test_non_team_routes_404_in_team_only_mode(): void
    {
        putenv('APP_MODE=team_only');

        $this->get('/settings')->assertNotFound();
        $this->get('/notes')->assertNotFound();
        $this->get('/data/export/data')->assertNotFound();
    }

    public function test_login_reachable_in_team_only_mode(): void
    {
        putenv('APP_MODE=team_only');

        $this->get('/login')->assertOk();
    }

    public function test_root_redirects_to_team_tasks_in_team_only_mode(): void
    {
        putenv('APP_MODE=team_only');

        $this->get('/')->assertRedirect('/team/tasks');
    }

    public function test_team_routes_require_auth_in_team_only_mode(): void
    {
        putenv('APP_MODE=team_only');
        $this->artisan('migrate', ['--database' => 'supabase', '--path' => 'database/migrations/team']);

        $this->get('/team/tasks')->assertRedirect(route('login'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/RestrictToTeamOnlyMiddlewareTest.php -v`
Expected: FAIL — `/settings` still returns 200 in team_only mode (middleware doesn't exist yet), root `/` doesn't redirect, `/team/tasks` doesn't require auth.

- [ ] **Step 3: Create `RestrictToTeamOnly` middleware**

```php
// app/Http/Middleware/RestrictToTeamOnly.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cuando APP_MODE=team_only (instancia pública del Kanban de equipo en el
 * VPS), bloquea con 404 cualquier ruta que no sea del Kanban, login/logout,
 * o el health check — no solo se esconde en la UI, es inalcanzable aunque
 * se sepa la URL exacta. En modo normal (local) no hace nada.
 */
class RestrictToTeamOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        if (env('APP_MODE') !== 'team_only') {
            return $next($request);
        }

        if ($request->is('/', 'team', 'team/*', 'login', 'logout', 'up')) {
            return $next($request);
        }

        abort(404);
    }
}
```

- [ ] **Step 4: Register it globally in `bootstrap/app.php`**

Modify the `->withMiddleware(...)` closure:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
        \App\Http\Middleware\SetLocale::class,
        \App\Http\Middleware\RestrictToTeamOnly::class,
    ]);
    $middleware->alias([
        'api.token' => \App\Http\Middleware\ApiToken::class,
    ]);
})
```

- [ ] **Step 5: Add the root redirect in `routes/web.php`**

Find:

```php
// En Render (APP_MODE=team_only) redirigir la raíz del kanban al equipo
if (env('APP_MODE') === 'team_only') {
    Route::redirect('/tasks', '/team/tasks');
}
```

Replace with:

```php
// En modo team_only (instancia pública del Kanban) redirigir la raíz al equipo
if (env('APP_MODE') === 'team_only') {
    Route::redirect('/tasks', '/team/tasks');
    Route::redirect('/', '/team/tasks');
}
```

- [ ] **Step 6: Make `auth` conditional on the `/team/*` group**

Find:

```php
Route::middleware([EnsureTeamEnabled::class, RestoreTeamIdentity::class])->group(function () {
```

Replace with:

```php
$teamMiddleware = [EnsureTeamEnabled::class, RestoreTeamIdentity::class];
if (env('APP_MODE') === 'team_only') {
    $teamMiddleware[] = 'auth';
}

Route::middleware($teamMiddleware)->group(function () {
```

- [ ] **Step 7: Run test to verify it passes**

```bash
php artisan test tests/Feature/RestrictToTeamOnlyMiddlewareTest.php -v
```
Expected: 5 tests pass.

- [ ] **Step 8: Run full suite for regressions**

```bash
php artisan test 2>&1 | tail -5
```
Expected: no regressions (292 total). This is the most important check in
this task — confirm no local-mode test broke, since `RestrictToTeamOnly` now
runs on every single request.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Middleware/RestrictToTeamOnly.php \
        bootstrap/app.php \
        routes/web.php \
        tests/Feature/RestrictToTeamOnlyMiddlewareTest.php
git commit -m "feat(team): bloquear todo lo que no es /team/* en modo team_only"
```

---

### Task 5: Ocultar el selector de identidad y mostrar "Cerrar sesión" en modo `team_only`

**Files:**
- Modify: `resources/views/tasks/board.blade.php`
- Modify: `resources/js/kanban.js`

**Interfaces:**
- Consumes: `env('APP_MODE')`, `window.KANBAN_ROUTES` (existing), the
  `logout` named route (Task 2).
- Produces: `window.KANBAN_TEAM_ONLY` (bool) and
  `window.KANBAN_ROUTES.logout` (string) available to `kanban.js`. The
  `#identity-modal` dialog is not rendered at all in `team_only` mode (login
  already fixed who you are — letting anyone reopen the picker and become a
  different member would defeat the login). `renderPastilla()` shows
  "Cerrar sesión" instead of "Cambiar" in that mode.

- [ ] **Step 1: Guard the identity modal in `board.blade.php`**

Find (around line 297):

```blade
@if($mode === 'team' && isset($members) && $members->isNotEmpty())
@php $activeMemberId = session('team_member_id') ? (int) session('team_member_id') : null @endphp
<dialog id="identity-modal" class="modal">
```

Replace the opening condition with:

```blade
@if($mode === 'team' && isset($members) && $members->isNotEmpty() && env('APP_MODE') !== 'team_only')
@php $activeMemberId = session('team_member_id') ? (int) session('team_member_id') : null @endphp
<dialog id="identity-modal" class="modal">
```

(The matching `@endif` that already closes this block stays untouched — only
the opening `@if` condition changes.)

- [ ] **Step 2: Expose `KANBAN_TEAM_ONLY` and the logout route to JS**

Find (around line 325-340):

```blade
<script>
window.KANBAN_MODE = '{{ $mode }}';
window.KANBAN_ROUTES = {
    store:         '{{ $mode === "team" ? route("team.tasks.store")   : route("tasks.store") }}',
    move:          '{{ $mode === "team" ? "/team/tasks"               : "/tasks" }}',
    update:        '{{ $mode === "team" ? "/team/tasks"               : "/tasks" }}',
    peek:          '{{ $mode === "team" ? route("team.tasks.peek")    : route("tasks.peek") }}',
    checkboxStore: '{{ $mode === "team" ? "/team/tasks" : "/tasks" }}',
    commentStore:  '{{ $mode === "team" ? "/team/tasks" : "/tasks" }}',
    identityStore:   '{{ route("team.identity.store") }}',
    identityClear:   '{{ route("team.identity.destroy") }}',
    transferPreview: '/tasks',
    transfer:        '/tasks',
    @if(isset($columnDraggable) && $columnDraggable)
    updateColumns:   '{{ route("team.projects.columns", $project) }}',
    @endif
};
```

Replace with:

```blade
<script>
window.KANBAN_MODE      = '{{ $mode }}';
window.KANBAN_TEAM_ONLY = @json(env('APP_MODE') === 'team_only');
window.KANBAN_ROUTES = {
    store:         '{{ $mode === "team" ? route("team.tasks.store")   : route("tasks.store") }}',
    move:          '{{ $mode === "team" ? "/team/tasks"               : "/tasks" }}',
    update:        '{{ $mode === "team" ? "/team/tasks"               : "/tasks" }}',
    peek:          '{{ $mode === "team" ? route("team.tasks.peek")    : route("tasks.peek") }}',
    checkboxStore: '{{ $mode === "team" ? "/team/tasks" : "/tasks" }}',
    commentStore:  '{{ $mode === "team" ? "/team/tasks" : "/tasks" }}',
    identityStore:   '{{ route("team.identity.store") }}',
    identityClear:   '{{ route("team.identity.destroy") }}',
    transferPreview: '/tasks',
    transfer:        '/tasks',
    logout:          '{{ route("logout") }}',
    @if(isset($columnDraggable) && $columnDraggable)
    updateColumns:   '{{ route("team.projects.columns", $project) }}',
    @endif
};
```

- [ ] **Step 3: Update `renderPastilla()` in `kanban.js`**

Find:

```javascript
function renderPastilla(memberId) {
    const el = document.getElementById('identity-pastilla');
    if (! el) return;
    if (! memberId) { el.innerHTML = ''; return; }

    const members = window.TEAM_MEMBERS || [];
    const m = members.find((x) => String(x.id) === String(memberId));
    if (! m) return;

    el.innerHTML = `
        <span class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold text-white select-none"
              style="background-color:${escape(m.color)}">${escape(m.initials)}</span>
        <span class="text-faint">${escape(m.name)}</span>
        <button type="button" id="btn-change-identity" class="text-xs text-faint hover:underline">Cambiar</button>
    `;
}
```

Replace with:

```javascript
function renderPastilla(memberId) {
    const el = document.getElementById('identity-pastilla');
    if (! el) return;
    if (! memberId) { el.innerHTML = ''; return; }

    const members = window.TEAM_MEMBERS || [];
    const m = members.find((x) => String(x.id) === String(memberId));
    if (! m) return;

    const changeAction = window.KANBAN_TEAM_ONLY
        ? `<form method="POST" action="${window.KANBAN_ROUTES.logout}" class="inline">
               <input type="hidden" name="_token" value="${csrf}">
               <button type="submit" class="text-xs text-faint hover:underline">Cerrar sesión</button>
           </form>`
        : `<button type="button" id="btn-change-identity" class="text-xs text-faint hover:underline">Cambiar</button>`;

    el.innerHTML = `
        <span class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold text-white select-none"
              style="background-color:${escape(m.color)}">${escape(m.initials)}</span>
        <span class="text-faint">${escape(m.name)}</span>
        ${changeAction}
    `;
}
```

- [ ] **Step 4: Rebuild assets**

```bash
npm run build 2>&1 | tail -3
```
Expected: `✓ built in ...s`, no errors.

- [ ] **Step 5: Run full suite for regressions**

```bash
php artisan test 2>&1 | tail -5
```
Expected: no regressions — this task has no new PHP tests (pure Blade/JS),
existing team board tests must still pass since `$mode`/`env('APP_MODE')`
default (unset) preserves current local behavior exactly.

- [ ] **Step 6: Manual verification**

```bash
APP_MODE=team_only php artisan serve --port=8100
```

Open `http://127.0.0.1:8100/team/tasks` (after logging in manually via
`/login` with a user created through `php artisan tinker` for this check).
Confirm: no "¿Quién eres tú?" modal appears, the pastilla shows "Cerrar
sesión" instead of "Cambiar", and clicking it logs out and redirects to
`/login`.

- [ ] **Step 7: Commit**

```bash
git add resources/views/tasks/board.blade.php resources/js/kanban.js
git commit -m "feat(team): ocultar el selector de identidad y mostrar logout en modo team_only"
```

---

### Task 6: Docker Compose + Caddy para el VPS

**Files:**
- Create: `../infra/docker-compose.yml`
- Create: `../infra/Caddyfile`

**Interfaces:**
- Consumes: `../dashboard/Dockerfile` (existing, unmodified).
- Produces: a `docker compose up -d` stack with two services — `app`
  (the existing Dockerfile) and `caddy` (reverse proxy + automatic TLS) —
  reachable on the VPS's domain.

- [ ] **Step 1: Create `infra/docker-compose.yml`**

```yaml
# infra/docker-compose.yml
services:
  app:
    build:
      context: ../dashboard
      dockerfile: Dockerfile
    restart: unless-stopped
    env_file:
      - .env.team
    environment:
      PORT: "10000"
    volumes:
      - app-storage:/app/storage
    expose:
      - "10000"

  caddy:
    image: caddy:2-alpine
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./Caddyfile:/etc/caddy/Caddyfile:ro
      - caddy-data:/data
      - caddy-config:/config
    depends_on:
      - app

volumes:
  app-storage:
  caddy-data:
  caddy-config:
```

- [ ] **Step 2: Create `infra/Caddyfile`**

```
# infra/Caddyfile
# Sustituye kanban.tudominio.com por el subdominio real antes de desplegar.
kanban.tudominio.com {
    reverse_proxy app:10000
}
```

- [ ] **Step 3: Document the required `.env.team` file (not committed)**

`infra/.env.team` is the file `env_file` points to — it holds real secrets
(`SUPABASE_DB_PASSWORD`, `APP_KEY`, `TEAM_USER_*_PASSWORD`, etc.) and must
**never** be committed. Add it to `../.gitignore`:

```bash
echo "infra/.env.team" >> ../.gitignore
```

It must contain, at minimum, everything the app needs at runtime:
`APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY=` (generate with
`php artisan key:generate --show` locally and paste the value),
`APP_MODE=team_only`, `DB_CONNECTION=sqlite`, `DB_DATABASE=/app/database/database.sqlite`,
all `SUPABASE_DB_*` / `SUPABASE_URL` / `SUPABASE_ANON_KEY` /
`SUPABASE_SERVICE_ROLE_KEY` vars (same values already used by the local
dashboard's `.env`), and the `TEAM_USER_{1,2,3}_*` vars from Task 3.

- [ ] **Step 4: Manual verification (requires Docker installed locally or on the VPS)**

```bash
cd infra
docker compose build
docker compose up -d
docker compose logs -f app
```
Expected: `app` container starts, `start.sh` runs migrations and serves on
port from `$PORT`. From another terminal:

```bash
docker compose exec app php artisan tinker --execute="echo \App\Models\User::count();"
```
Expected: no connection errors (confirms the `sqlite` DB inside the
container is reachable). Stop with `docker compose down` once verified —
this is infra config, not something PHPUnit can assert.

- [ ] **Step 5: Commit**

```bash
git add ../infra/docker-compose.yml ../infra/Caddyfile ../.gitignore
git commit -m "feat(deploy): añadir docker-compose y Caddyfile para el VPS del equipo"
```

---

### Task 7: Script de despliegue para el VPS

**Files:**
- Create: `../infra/deploy.sh`

**Interfaces:**
- Consumes: `../infra/docker-compose.yml` (Task 6).
- Produces: a single command (`bash infra/deploy.sh`, run over SSH on the
  VPS) that pulls the latest code and rebuilds/restarts the stack — same
  "one script" pattern already used for `desktop/rebuild.sh`.

- [ ] **Step 1: Create `infra/deploy.sh`**

```bash
#!/usr/bin/env bash
# Actualiza y redespliega el Kanban público en el VPS.
# Uso (en el VPS, dentro del checkout del repo): bash infra/deploy.sh
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"

echo "[deploy] git pull..."
git -C "$REPO_ROOT" pull

echo "[deploy] docker compose build + up..."
cd "$REPO_ROOT/infra"
docker compose build
docker compose up -d

echo "[deploy] Listo. Logs: docker compose -f infra/docker-compose.yml logs -f app"
```

- [ ] **Step 2: Make it executable**

```bash
chmod +x ../infra/deploy.sh
```

- [ ] **Step 3: Verify the syntax**

```bash
bash -n ../infra/deploy.sh && echo "sintaxis OK"
```
Expected: `sintaxis OK`. (Full end-to-end run requires the actual VPS with
Docker and the git remote configured — document as a manual step for first
real deploy, not something to fake-test here.)

- [ ] **Step 4: Commit**

```bash
git add ../infra/deploy.sh
git commit -m "feat(deploy): añadir script de un comando para redesplegar en el VPS"
```

---

### Task 8: Retirar los artefactos de Render

**Files:**
- Delete: `../render.yaml`
- Delete: `../.github/workflows/keep-alive.yml`

**Interfaces:**
- Consumes: nothing (pure removal).
- Produces: no dead deployment config referencing the abandoned Render path.

- [ ] **Step 1: Remove the files**

```bash
cd ..
git rm render.yaml .github/workflows/keep-alive.yml
```

- [ ] **Step 2: Verify nothing else references them**

```bash
grep -rn "render.yaml\|keep-alive" --include="*.md" --include="*.php" --include="*.yml" . 2>/dev/null | grep -v docs/superpowers/plans
```
Expected: no output (the only remaining mentions are in historical plan
docs under `docs/superpowers/plans/`, which stay as a record of what was
tried and superseded).

- [ ] **Step 3: Commit**

```bash
git commit -m "chore(deploy): retirar render.yaml y el keep-alive de GitHub Actions

Se sustituyen por el despliegue en VPS propio (infra/) — Render se
descartó como proveedor (plan gratis que duerme, hack de keep-alive)."
```

---

## Self-Review

### Spec coverage

| Spec requirement | Task |
|---|---|
| Mismo codebase, dos despliegues (local sin login / público team_only) | Tasks 4, 5, 6 |
| Tabla `users` local + login/logout | Tasks 1, 2 |
| Login = identidad (session team_member_id/name) | Task 2 |
| Cuentas reproducibles sin secretos en git | Task 3 |
| Bloqueo de rutas no-team en modo team_only (404 real, no solo UI) | Task 4 |
| Redirect de `/` a `/team/tasks` en team_only | Task 4 |
| `auth` obligatorio en `/team/*` solo en team_only | Task 4 |
| Ocultar selector de identidad / logout visible en team_only | Task 5 |
| Docker Compose + Caddy en VPS, reutilizando Dockerfile | Task 6 |
| Script de un comando para redesplegar | Task 7 |
| Retirar render.yaml y keep-alive | Task 8 |

Todos los requisitos del spec cubiertos. Sin gaps.

### Consistencia de tipos

- `session('team_member_id')` se guarda como `int` (Task 2, `$member->id`)
  y se lee como `int` en `TeamTaskController`/`TeamTaskCommentController`/etc.
  (ya existente, sin cambios) — consistente.
- `User::$fillable` incluye `team_member_id` (Task 1), usado por
  `TeamUsersSeeder::updateOrCreate` (Task 3) y por `LoginController::store`
  (Task 2, vía `$user->team_member_id`) — consistente.
- `window.KANBAN_TEAM_ONLY` y `window.KANBAN_ROUTES.logout` definidos en
  Task 5 (Blade), consumidos en la misma Task (kanban.js) — consistente, sin
  otros consumidores que pudieran desincronizarse.
- El middleware `RestrictToTeamOnly` (Task 4) y el guard de Blade
  `env('APP_MODE') !== 'team_only'` (Task 5) usan la misma comprobación
  (`env('APP_MODE')`), coherente con el estilo ya existente en
  `routes/web.php`.
