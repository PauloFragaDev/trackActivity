<?php

namespace Tests\Feature;

use App\Models\TeamMember;
use App\Models\User;
use Database\Seeders\TeamUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamUsersSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--database' => 'supabase', '--path' => 'database/migrations/team']);
    }

    protected function tearDown(): void
    {
        foreach (['EMAIL', 'PASSWORD', 'NAME', 'MEMBER_ID'] as $suffix) {
            putenv("TEAM_USER_1_{$suffix}");
        }
        parent::tearDown();
    }

    public function test_creates_user_from_env_vars(): void
    {
        $member = TeamMember::create(['name' => 'Ana', 'color' => '#6366f1', 'position' => 0]);
        putenv('TEAM_USER_1_EMAIL=ana@example.com');
        putenv('TEAM_USER_1_PASSWORD=secret123');
        putenv('TEAM_USER_1_NAME=Ana');
        putenv("TEAM_USER_1_MEMBER_ID={$member->id}");

        (new TeamUsersSeeder())->run();

        $this->assertDatabaseHas('users', [
            'email'          => 'ana@example.com',
            'name'           => 'Ana',
            'team_member_id' => $member->id,
        ], 'supabase');
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

    public function test_does_not_write_when_nothing_changed(): void
    {
        $member = TeamMember::create(['name' => 'Ana', 'color' => '#6366f1', 'position' => 0]);
        putenv('TEAM_USER_1_EMAIL=ana@example.com');
        putenv('TEAM_USER_1_PASSWORD=secret123');
        putenv('TEAM_USER_1_NAME=Ana');
        putenv("TEAM_USER_1_MEMBER_ID={$member->id}");

        (new TeamUsersSeeder())->run();

        $updatedAt = User::where('email', 'ana@example.com')->firstOrFail()->updated_at;

        // Avanzamos el reloj para que, si el seeder disparase un UPDATE real
        // (aunque solo fuese por refrescar los timestamps), `updated_at`
        // cambiaría y lo detectaríamos.
        $this->travel(1)->hour();

        (new TeamUsersSeeder())->run();

        $this->assertTrue(
            $updatedAt->equalTo(
                User::where('email', 'ana@example.com')->firstOrFail()->updated_at
            ),
            'El seeder no debería reescribir el usuario cuando ningún dato cambió.'
        );
    }

    public function test_rotates_password_hash_when_env_password_changes(): void
    {
        putenv('TEAM_USER_1_EMAIL=ana@example.com');
        putenv('TEAM_USER_1_PASSWORD=secret123');

        (new TeamUsersSeeder())->run();

        $originalHash = User::where('email', 'ana@example.com')->firstOrFail()->password;

        $this->travel(1)->hour();

        putenv('TEAM_USER_1_PASSWORD=new-secret-456');
        (new TeamUsersSeeder())->run();

        $user = User::where('email', 'ana@example.com')->firstOrFail();

        $this->assertNotSame($originalHash, $user->password);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('new-secret-456', $user->password));
    }
}
