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
