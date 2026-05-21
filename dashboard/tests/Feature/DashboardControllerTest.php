<?php

namespace Tests\Feature;

use App\Models\Note;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
