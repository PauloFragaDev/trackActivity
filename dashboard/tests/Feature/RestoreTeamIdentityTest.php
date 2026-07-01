<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\TeamMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestoreTeamIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--database' => 'supabase', '--path' => 'database/migrations/team']);
    }

    public function test_restores_session_from_persisted_setting_when_session_is_empty(): void
    {
        $member = TeamMember::create(['name' => 'Ana García', 'color' => '#6366f1', 'position' => 0]);
        Setting::set('team.member_id', $member->id);

        $this->get('/team/tasks')->assertOk();

        $this->assertEquals($member->id, session('team_member_id'));
        $this->assertEquals('Ana García', session('team_member_name'));
    }

    public function test_does_not_restore_when_saved_member_no_longer_exists(): void
    {
        Setting::set('team.member_id', 9999);

        $this->get('/team/tasks')->assertOk();

        $this->assertNull(session('team_member_id'));
    }

    public function test_does_not_override_an_already_active_session(): void
    {
        $other = TeamMember::create(['name' => 'Otro', 'color' => '#111111', 'position' => 1]);
        session(['team_member_id' => $other->id, 'team_member_name' => $other->name]);
        Setting::set('team.member_id', 9999);

        $this->get('/team/tasks')->assertOk();

        $this->assertEquals($other->id, session('team_member_id'));
    }
}
