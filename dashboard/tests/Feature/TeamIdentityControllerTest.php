<?php

namespace Tests\Feature;

use App\Models\TeamMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamIdentityControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--database' => 'supabase', '--path' => 'database/migrations/team']);
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
}
