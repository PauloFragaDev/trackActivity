<?php

namespace App\Http\Controllers;

use App\Services\PomodoroService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Página de ajustes single-user. De momento sólo hay sección Pomodoro;
 * cuando aparezcan más preferencias, se reutiliza el mismo controller.
 */
class SettingsController extends Controller
{
    public function __construct(private readonly PomodoroService $pomodoro) {}

    public function pomodoro(): View
    {
        return view('settings.pomodoro', [
            'config' => $this->pomodoro->currentConfig(),
        ]);
    }

    public function savePomodoro(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pomodoro_focus_min'         => ['required', 'integer', 'between:5,120'],
            'pomodoro_short_break_min'   => ['required', 'integer', 'between:1,30'],
            'pomodoro_long_break_min'    => ['required', 'integer', 'between:5,60'],
            'pomodoro_cycles_until_long' => ['required', 'integer', 'between:2,10'],
            'pomodoro_daily_goal_min'    => ['required', 'integer', 'between:15,720'],
        ]);

        $this->pomodoro->saveConfig($data);
        return redirect()
            ->route('settings.pomodoro')
            ->with('status', 'Pomodoro actualizado.');
    }
}
