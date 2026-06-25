<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamEnabledMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--database' => 'supabase', '--path' => 'database/migrations/team']);
    }

    public function test_team_routes_accessible_when_enabled(): void
    {
        Setting::set('modules.team', true);

        $this->get('/team/tasks')->assertStatus(200);
    }

    public function test_team_routes_redirect_when_disabled(): void
    {
        Setting::set('modules.team', false);

        $this->get('/team/tasks')->assertRedirect('/tasks');
    }

    public function test_team_enabled_defaults_to_true(): void
    {
        // No setting stored — default is true
        $this->get('/team/tasks')->assertStatus(200);
    }
}
