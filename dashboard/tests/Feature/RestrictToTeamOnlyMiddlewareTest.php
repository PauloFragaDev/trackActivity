<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
use Tests\TestCase;

class RestrictToTeamOnlyMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Dos de estos tests ('root redirects', 'team routes require auth')
     * dependen de bloques `if (env('APP_MODE') === 'team_only')` en
     * routes/web.php que solo se evalúan UNA VEZ, cuando el framework
     * arranca y registra las rutas — no en cada request. Por eso el
     * APP_MODE tiene que estar fijado *antes* de que `parent::setUp()`
     * arranque la aplicación, no dentro del cuerpo del test.
     *
     * Además, Illuminate\Support\Env cachea un Repository estático durante
     * todo el proceso de PHPUnit. Su ImmutableWriter recuerda qué claves ya
     * "cargó" desde el .env una vez — y a partir de ahí, cada rearranque de
     * la app (cada test) vuelve a pisar esa clave con el valor del .env, sin
     * importar qué hayamos puesto antes con putenv()/$_ENV/$_SERVER. Por eso
     * hace falta Env::enablePutenv(), que resetea ese Repository cacheado,
     * antes de fijar el valor — así el siguiente arranque lo trata como
     * "definido externamente" y no lo sobrescribe.
     *
     * Convención: cualquier test cuyo nombre contenga "team_only_mode"
     * arranca con APP_MODE=team_only ya fijado.
     */
    protected function setUp(): void
    {
        if (str_contains($this->name(), 'team_only_mode')) {
            Env::enablePutenv();
            putenv('APP_MODE=team_only');
            $_ENV['APP_MODE']    = 'team_only';
            $_SERVER['APP_MODE'] = 'team_only';
        }

        parent::setUp();
    }

    protected function tearDown(): void
    {
        putenv('APP_MODE');
        unset($_ENV['APP_MODE'], $_SERVER['APP_MODE']);
        Env::enablePutenv();
        parent::tearDown();
    }

    public function test_non_team_routes_pass_through_when_app_mode_is_not_team_only(): void
    {
        // /settings itself redirects (302) to /settings/general regardless of
        // this middleware, so assert against a route that renders directly.
        $this->get('/settings/general')->assertOk();
    }

    public function test_non_team_routes_404_in_team_only_mode(): void
    {
        $this->get('/settings')->assertNotFound();
        $this->get('/notes')->assertNotFound();
        $this->get('/data/export/data')->assertNotFound();
    }

    public function test_login_reachable_in_team_only_mode(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_root_redirects_to_team_tasks_in_team_only_mode(): void
    {
        $this->get('/')->assertRedirect('/team/tasks');
    }

    public function test_team_routes_require_auth_in_team_only_mode(): void
    {
        $this->artisan('migrate', ['--database' => 'supabase', '--path' => 'database/migrations/team']);

        $this->get('/team/tasks')->assertRedirect(route('login'));
    }
}
