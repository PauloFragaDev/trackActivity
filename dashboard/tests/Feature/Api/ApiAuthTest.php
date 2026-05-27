<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_503_when_api_token_not_configured(): void
    {
        config(['app.api_token' => '']);
        $this->getJson('/api/ping')
            ->assertStatus(503)
            ->assertJson(['error' => 'API_TOKEN no configurado en el servidor.']);
    }

    public function test_returns_401_without_bearer_token(): void
    {
        config(['app.api_token' => 'secret']);
        $this->getJson('/api/ping')->assertStatus(401);
    }

    public function test_returns_401_with_wrong_bearer_token(): void
    {
        config(['app.api_token' => 'secret']);
        $this->withHeaders(['Authorization' => 'Bearer wrong'])
            ->getJson('/api/ping')->assertStatus(401);
    }

    public function test_returns_200_with_correct_bearer_token(): void
    {
        config(['app.api_token' => 'secret']);
        $this->withHeaders(['Authorization' => 'Bearer secret'])
            ->getJson('/api/ping')
            ->assertOk()
            ->assertJson(['ok' => true, 'service' => 'trackActivity']);
    }
}
