<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\UserIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_is_generated_and_stable(): void
    {
        $token = UserIdentity::token();

        $this->assertNotEmpty($token);
        $this->assertSame($token, UserIdentity::token());   // no cambia entre llamadas
        $this->assertSame($token, Setting::get('user.token'));
    }

    public function test_name_defaults_to_empty_string(): void
    {
        $this->assertSame('', UserIdentity::name());
    }

    public function test_set_name_persists_trimmed(): void
    {
        UserIdentity::setName('  Paulo  ');

        $this->assertSame('Paulo', UserIdentity::name());
        $this->assertSame('Paulo', Setting::get('user.name'));
    }
}
