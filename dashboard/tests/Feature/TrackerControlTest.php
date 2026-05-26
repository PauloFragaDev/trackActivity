<?php

namespace Tests\Feature;

use App\Services\TrackerManager;
use Tests\TestCase;

class TrackerControlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        @unlink(config('tracker.pid_file'));
    }

    public function test_status_returns_not_running_without_pid_file(): void
    {
        $this->assertFalse(app(TrackerManager::class)->status()['running']);
    }

    public function test_stale_pid_file_is_cleaned_up(): void
    {
        // El PID de este proceso de tests sí existe, pero su cmdline no
        // corresponde al tracker → debe descartarse y limpiar el fichero.
        file_put_contents(config('tracker.pid_file'), getmypid());

        $this->assertFalse(app(TrackerManager::class)->status()['running']);
        $this->assertFileDoesNotExist(config('tracker.pid_file'));
    }

    public function test_toggle_route_redirects(): void
    {
        // Con tracker.bin = /nonexistent/..., start() falla con gracia y el
        // controlador hace un back() con flash. No se arranca nada.
        $this->post('/tracker/toggle')->assertRedirect();
    }

    protected function tearDown(): void
    {
        @unlink(config('tracker.pid_file'));
        parent::tearDown();
    }
}
