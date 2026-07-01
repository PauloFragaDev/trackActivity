<?php

namespace Tests\Feature;

use App\Services\SchedulerManager;
use App\Services\TrackerManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackerControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        @unlink(config('tracker.pid_file'));
        @unlink(config('tracker.scheduler.pid_file'));
    }

    public function test_tracker_status_returns_not_running_without_pid_file(): void
    {
        $this->assertFalse(app(TrackerManager::class)->status()['running']);
    }

    public function test_tracker_stale_pid_file_is_cleaned_up(): void
    {
        // El PID de este proceso de tests sí existe, pero su cmdline no
        // corresponde al tracker → debe descartarse y limpiar el fichero.
        file_put_contents(config('tracker.pid_file'), getmypid());

        $this->assertFalse(app(TrackerManager::class)->status()['running']);
        $this->assertFileDoesNotExist(config('tracker.pid_file'));
    }

    public function test_scheduler_status_returns_not_running_without_pid_file(): void
    {
        $this->assertFalse(app(SchedulerManager::class)->status()['running']);
    }

    public function test_scheduler_stale_pid_file_is_cleaned_up(): void
    {
        // El identifier en tests es "__test_scheduler_no_match__" → ningún
        // proceso real lo lleva en su cmdline.
        file_put_contents(config('tracker.scheduler.pid_file'), getmypid());

        $this->assertFalse(app(SchedulerManager::class)->status()['running']);
        $this->assertFileDoesNotExist(config('tracker.scheduler.pid_file'));
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
        @unlink(config('tracker.scheduler.pid_file'));
        parent::tearDown();
    }
}
