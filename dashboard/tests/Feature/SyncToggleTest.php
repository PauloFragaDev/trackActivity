<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncToggleTest extends TestCase
{
    use RefreshDatabase;

    public function test_save_sync_persists_flags(): void
    {
        // Checkbox marcado = '1'; ausente = off.
        $this->post('/settings/sync', ['crm' => '1'])->assertRedirect();

        $this->assertTrue((bool) Setting::get('sync.crm'));
        $this->assertFalse((bool) Setting::get('sync.extension'));
    }

    public function test_extension_sync_disabled_returns_403(): void
    {
        config(['app.api_token' => 'secret']);
        Setting::set('sync.extension', false);

        $this->withHeaders(['Authorization' => 'Bearer secret'])
            ->postJson('/api/sync/kanban', [])
            ->assertStatus(403);
    }

    public function test_extension_sync_enabled_passes_gate(): void
    {
        config(['app.api_token' => 'secret']);
        Setting::set('sync.extension', true);

        // Gate abierto → la petición llega a validación (422), no 403.
        $this->withHeaders(['Authorization' => 'Bearer secret'])
            ->postJson('/api/sync/kanban', [])
            ->assertStatus(422);
    }
}
