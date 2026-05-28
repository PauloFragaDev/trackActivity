<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\AppearanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppearanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_theme_when_setting_empty(): void
    {
        $this->assertSame('default', AppearanceService::current());
    }

    public function test_save_persists_valid_theme(): void
    {
        $this->postJson('/settings/appearance', ['theme_id' => 'paper'])
            ->assertOk()
            ->assertExactJson(['theme_id' => 'paper']);

        $this->assertSame('paper', Setting::get(AppearanceService::SETTING_KEY));
    }

    public function test_save_falls_back_to_default_for_unknown_id(): void
    {
        $this->postJson('/settings/appearance', ['theme_id' => 'made-up-theme'])
            ->assertOk()
            ->assertExactJson(['theme_id' => 'default']);
    }

    public function test_appearance_page_marks_current_theme_active(): void
    {
        Setting::set(AppearanceService::SETTING_KEY, 'notion');

        $this->get('/settings/appearance')
            ->assertOk()
            ->assertSee('data-current="notion"', false);
    }
}
