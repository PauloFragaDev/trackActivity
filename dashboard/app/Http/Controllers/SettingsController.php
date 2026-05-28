<?php

namespace App\Http\Controllers;

use App\Services\ModuleVisibility;
use App\Services\PomodoroService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Hub de ajustes single-user.
 *
 * `/settings` redirige a la sección General. Cada sección comparte
 * el layout `layouts.settings`, que añade un mini-sidebar a la izquierda
 * con todas las subsecciones (general, proyectos, etiquetas, pomodoro,
 * exportar, datos). Las URLs originales de cada subsección se mantienen
 * para no romper bookmarks.
 */
class SettingsController extends Controller
{
    public function __construct(private readonly PomodoroService $pomodoro) {}

    /** Entry-point del hub. Redirige a la primera sección. */
    public function index(): RedirectResponse
    {
        return redirect()->route('settings.general');
    }

    public function general(): View
    {
        // `$modules` ya está compartido por AppServiceProvider via View::share,
        // pero pasarlo explícito documenta la dependencia del controller.
        return view('settings.general', [
            'modules' => ModuleVisibility::all(),
        ]);
    }

    public function saveGeneral(Request $request): RedirectResponse
    {
        // Los checkboxes desmarcados no envían valor: el service interpreta
        // "clave ausente" = desactivado, así que no validamos cada uno.
        $submitted = $request->input('modules', []);
        ModuleVisibility::saveAll(is_array($submitted) ? $submitted : []);

        return redirect()
            ->route('settings.general')
            ->with('status', 'Ajustes guardados.');
    }

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
        ]);

        $this->pomodoro->saveConfig($data);
        return redirect()
            ->route('settings.pomodoro')
            ->with('status', 'Pomodoro actualizado.');
    }
}
