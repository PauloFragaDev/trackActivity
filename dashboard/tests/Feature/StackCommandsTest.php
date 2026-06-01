<?php

namespace Tests\Feature;

use App\Services\TrackerManager;
use Tests\TestCase;

class StackCommandsTest extends TestCase
{
    public function test_tracker_start_invokes_manager(): void
    {
        $mock = $this->mock(TrackerManager::class);
        $mock->shouldReceive('status')->andReturn(['running' => false]);
        $mock->shouldReceive('start')->once();

        $this->artisan('tracker:start')->assertExitCode(0);
    }

    public function test_tracker_start_is_noop_when_already_running(): void
    {
        $mock = $this->mock(TrackerManager::class);
        $mock->shouldReceive('status')->andReturn(['running' => true]);
        $mock->shouldNotReceive('start');

        $this->artisan('tracker:start')->assertExitCode(0);
    }

    public function test_tracker_start_reports_failure_gracefully(): void
    {
        $mock = $this->mock(TrackerManager::class);
        $mock->shouldReceive('status')->andReturn(['running' => false]);
        $mock->shouldReceive('start')->andThrow(new \RuntimeException('sin binario'));

        $this->artisan('tracker:start')->assertExitCode(1);
    }

    public function test_tracker_stop_invokes_manager(): void
    {
        $mock = $this->mock(TrackerManager::class);
        $mock->shouldReceive('stop')->once();

        $this->artisan('tracker:stop')->assertExitCode(0);
    }
}
