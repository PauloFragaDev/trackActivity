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
