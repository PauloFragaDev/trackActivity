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

    public function test_sixth_consecutive_failed_login_attempt_is_throttled(): void
    {
        User::create(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'secret123']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'ana@example.com', 'password' => 'wrong'])
                ->assertSessionHasErrors('email');
        }

        $this->post('/login', ['email' => 'ana@example.com', 'password' => 'wrong'])
            ->assertStatus(429);
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
