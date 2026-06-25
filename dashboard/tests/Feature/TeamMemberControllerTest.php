<?php

namespace Tests\Feature;

use App\Models\TeamMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamMemberControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ejecutar migraciones del equipo en la conexión supabase (SQLite :memory: en tests)
        $this->artisan('migrate', ['--database' => 'supabase', '--path' => 'database/migrations/team']);
    }

    public function test_index_returns_members_as_json(): void
    {
        TeamMember::create(['name' => 'Ana García', 'color' => '#ff0000', 'position' => 0]);

        $this->getJson('/team/members')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Ana García', 'color' => '#ff0000']);
    }

    public function test_store_creates_member(): void
    {
        $this->post('/team/members', [
            'name'  => 'Carlos López',
            'color' => '#00ff00',
        ])->assertRedirect();

        $this->assertDatabaseHas('team_members', ['name' => 'Carlos López'], 'supabase');
    }

    public function test_update_modifies_member(): void
    {
        $member = TeamMember::create(['name' => 'Original', 'color' => '#111111', 'position' => 0]);

        $this->patch("/team/members/{$member->id}", [
            'name'  => 'Actualizado',
            'color' => '#222222',
        ])->assertRedirect();

        $this->assertDatabaseHas('team_members', ['id' => $member->id, 'name' => 'Actualizado'], 'supabase');
    }

    public function test_destroy_deletes_member(): void
    {
        $member = TeamMember::create(['name' => 'Borrable', 'color' => '#333333', 'position' => 0]);

        $this->delete("/team/members/{$member->id}")->assertRedirect();

        $this->assertDatabaseMissing('team_members', ['id' => $member->id], 'supabase');
    }

    public function test_store_rejects_invalid_color(): void
    {
        $this->post('/team/members', [
            'name'  => 'Test',
            'color' => 'invalid-color',
        ])->assertSessionHasErrors('color');
    }
}
