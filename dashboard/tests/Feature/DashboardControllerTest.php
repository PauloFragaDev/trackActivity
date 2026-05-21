<?php

namespace Tests\Feature;

use App\Models\Note;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_loads(): void
    {
        $this->get('/dashboard')->assertOk();
    }

    public function test_dashboard_shows_recent_notes(): void
    {
        Note::create(['title' => 'Nota muy reciente']);

        $this->get('/dashboard')->assertOk()->assertSee('Nota muy reciente');
    }

    public function test_dashboard_warns_when_the_tracker_is_stale(): void
    {
        DB::table('activity_events')->insert([
            'occurred_at' => CarbonImmutable::now('UTC')->subHours(2)->format('Y-m-d H:i:s'),
            'source'      => 'window',
        ]);

        $this->get('/dashboard')->assertOk()->assertSee('no registra actividad');
    }

    public function test_dashboard_does_not_warn_when_the_tracker_is_recent(): void
    {
        DB::table('activity_events')->insert([
            'occurred_at' => CarbonImmutable::now('UTC')->subMinutes(2)->format('Y-m-d H:i:s'),
            'source'      => 'window',
        ]);

        $this->get('/dashboard')->assertOk()->assertDontSee('no registra actividad');
    }

    public function test_dashboard_shows_tasks_in_progress(): void
    {
        \App\Models\Task::create(['title' => 'Tarea en curso', 'status' => 'doing']);
        \App\Models\Task::create(['title' => 'Tarea pendiente', 'status' => 'todo']);

        $this->get('/dashboard')->assertOk()
            ->assertSee('Tarea en curso')
            ->assertDontSee('Tarea pendiente');
    }
}
