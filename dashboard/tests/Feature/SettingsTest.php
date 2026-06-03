<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_save_general_persists_user_name(): void
    {
        $this->post('/settings/general', ['user_name' => 'Paulo'])
            ->assertRedirect('/settings/general');

        $this->assertSame('Paulo', Setting::get('user.name'));
    }

    public function test_pomodoro_page_renders_with_defaults(): void
    {
        $this->get('/settings/pomodoro')
            ->assertOk()
            ->assertSee('Pomodoro')
            ->assertSee('value="25"', false); // default focus_min
    }

    public function test_save_pomodoro_persists_values_and_redirects(): void
    {
        $this->post('/settings/pomodoro', [
            'pomodoro_focus_min'         => 50,
            'pomodoro_short_break_min'   => 10,
            'pomodoro_long_break_min'    => 20,
            'pomodoro_cycles_until_long' => 3,
        ])
            ->assertRedirect('/settings/pomodoro')
            ->assertSessionHas('status');

        $this->assertSame(50, Setting::get('pomodoro_focus_min'));
        $this->assertSame(20, Setting::get('pomodoro_long_break_min'));
    }

    public function test_save_pomodoro_rejects_out_of_range(): void
    {
        $this->post('/settings/pomodoro', [
            'pomodoro_focus_min'         => 1, // < 5
            'pomodoro_short_break_min'   => 5,
            'pomodoro_long_break_min'    => 15,
            'pomodoro_cycles_until_long' => 4,
        ])->assertSessionHasErrors('pomodoro_focus_min');
    }

    public function test_setting_helpers_round_trip(): void
    {
        Setting::set('demo', ['a' => 1, 'b' => 'x']);
        $this->assertSame(['a' => 1, 'b' => 'x'], Setting::get('demo'));
        $this->assertSame('fallback', Setting::get('nope', 'fallback'));
    }
}
