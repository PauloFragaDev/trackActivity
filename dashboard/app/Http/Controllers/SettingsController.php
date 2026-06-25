<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\AppearanceService;
use App\Services\ModuleVisibility;
use App\Services\PomodoroService;
use App\Services\UserIdentity;
use Illuminate\Http\JsonResponse;
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
            'modules'  => ModuleVisibility::all(),
            'userName' => UserIdentity::name(),
        ]);
    }

    public function saveGeneral(Request $request): RedirectResponse
    {
        $request->validate([
            'user_name' => ['nullable', 'string', 'max:80'],
        ]);
        UserIdentity::setName((string) $request->input('user_name', ''));

        // Los checkboxes desmarcados no envían valor: el service interpreta
        // "clave ausente" = desactivado, así que no validamos cada uno.
        $submitted = $request->input('modules', []);
        ModuleVisibility::saveAll(is_array($submitted) ? $submitted : []);

        return redirect()
            ->route('settings.general')
            ->with('status', 'Ajustes guardados.');
    }

    public function appearance(): View
    {
        return view('settings.appearance', [
            'themes'  => AppearanceService::THEMES,
            'current' => AppearanceService::current(),
        ]);
    }

    /**
     * Cambio de tema "al instante": el cliente ya aplicó el cambio en el
     * DOM y localStorage; aquí solo persistimos para que sobreviva al
     * próximo reload (y a otros navegadores). Devuelve JSON, no hace
     * redirect, para no romper la UX inmediata.
     */
    public function saveAppearance(Request $request): JsonResponse
    {
        $id = (string) $request->input('theme_id', '');
        AppearanceService::setCurrent($id);
        return response()->json(['theme_id' => AppearanceService::current()]);
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

    public function integrations(): View
    {
        $supConnected = (bool) config('team.db_host');
        $members      = $supConnected ? \App\Models\TeamMember::orderBy('position')->get() : collect();

        return view('settings.integrations', [
            'supConnected' => $supConnected,
            'base44Url'    => Setting::get('base44.url', ''),
            'base44Token'  => Setting::get('base44.token', '') ? '••••••••' : '',
            'members'      => $members,
            'teamEnabled'  => (bool) Setting::get('team.enabled', true),
        ]);
    }

    public function saveIntegrations(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'base44_url'   => ['nullable', 'url', 'max:255'],
            'base44_token' => ['nullable', 'string', 'max:500'],
            'team_enabled' => ['sometimes', 'boolean'],
        ]);

        if ($data['base44_url'] !== null) {
            Setting::set('base44.url', $data['base44_url']);
        }
        // Solo actualizar el token si no es la máscara
        if ($data['base44_token'] && $data['base44_token'] !== '••••••••') {
            Setting::set('base44.token', $data['base44_token']);
        }
        Setting::set('team.enabled', $request->boolean('team_enabled', true));

        return redirect()->route('settings.integrations')->with('status', 'Ajustes de integración guardados.');
    }

    public function sync(): View
    {
        return view('settings.sync', [
            'crm'       => (bool) Setting::get('sync.crm', false),
            'extension' => (bool) Setting::get('sync.extension', true),
        ]);
    }

    public function saveSync(Request $request): RedirectResponse
    {
        Setting::set('sync.crm', $request->boolean('crm'));
        Setting::set('sync.extension', $request->boolean('extension'));

        return redirect()
            ->route('settings.sync')
            ->with('status', 'Ajustes de sincronización guardados.');
    }
}
