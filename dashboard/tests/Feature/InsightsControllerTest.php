<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InsightsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_insights_page_renders_day(): void
    {
        $this->get('/insights')->assertOk()->assertSee('Insights');
    }

    public function test_insights_page_renders_week(): void
    {
        $this->get('/insights?period=week')->assertOk()->assertSee('Semana');
    }

    public function test_home_shows_digest_when_module_enabled(): void
    {
        // Por defecto los módulos están activos (opt-out).
        $this->get('/dashboard')->assertOk()->assertSee('Resumen de hoy');
    }

    public function test_home_hides_digest_when_module_disabled(): void
    {
        Setting::set('modules.insights', false);

        $this->get('/dashboard')->assertOk()->assertDontSee('Resumen de hoy');
    }
}
