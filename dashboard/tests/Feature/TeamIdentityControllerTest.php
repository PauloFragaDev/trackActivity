<?php

namespace Tests\Feature;

use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
use Tests\TestCase;

class TeamIdentityControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * abort_if(config('app.mode') === 'team_only', ...) en el controlador
     * lee un valor resuelto en config/app.php al arrancar la app (no en
     * cada request), así que APP_MODE tiene que estar fijado *antes* de
     * que parent::setUp() arranque la aplicación — igual que en
     * RestrictToTeamOnlyMiddlewareTest. Convención: cualquier test cuyo
     * nombre contenga "team_only_mode" arranca con APP_MODE=team_only ya
     * fijado.
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
        $this->artisan('migrate', ['--database' => 'supabase', '--path' => 'database/migrations/team']);
    }

    protected function tearDown(): void
    {
        putenv('APP_MODE');
        unset($_ENV['APP_MODE'], $_SERVER['APP_MODE']);
        Env::enablePutenv();
        parent::tearDown();
    }

    public function test_store_saves_identity_to_session(): void
    {
        $member = TeamMember::create(['name' => 'Ana García', 'color' => '#6366f1', 'position' => 0]);

        $this->postJson('/team/identity', ['member_id' => $member->id])
            ->assertJson(['ok' => true]);

        $this->assertEquals($member->id, session('team_member_id'));
        $this->assertEquals('Ana García', session('team_member_name'));
    }

    public function test_store_rejects_invalid_member(): void
    {
        $this->postJson('/team/identity', ['member_id' => 9999])
            ->assertStatus(422);
    }

    public function test_destroy_clears_session(): void
    {
        session(['team_member_id' => 1, 'team_member_name' => 'Test']);

        $this->deleteJson('/team/identity')
            ->assertJson(['ok' => true]);

        $this->assertNull(session('team_member_id'));
    }

    public function test_store_forbidden_in_team_only_mode(): void
    {
        // En team_only las rutas /team/* exigen 'auth' además del guard de
        // este controlador — nos autenticamos para llegar hasta el abort_if.
        $member = TeamMember::create(['name' => 'Ana García', 'color' => '#6366f1', 'position' => 0]);
        $user   = User::create([
            'name' => 'Ana García', 'email' => 'ana@example.com', 'password' => 'secret123',
        ]);
        $this->actingAs($user);

        $this->postJson('/team/identity', ['member_id' => $member->id])
            ->assertForbidden();
    }

    public function test_destroy_forbidden_in_team_only_mode(): void
    {
        $user = User::create([
            'name' => 'Ana García', 'email' => 'ana@example.com', 'password' => 'secret123',
        ]);
        $this->actingAs($user);
        session(['team_member_id' => 1, 'team_member_name' => 'Test']);

        $this->deleteJson('/team/identity')
            ->assertForbidden();
    }
}
