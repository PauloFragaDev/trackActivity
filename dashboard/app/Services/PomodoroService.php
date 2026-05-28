<?php

namespace App\Services;

use App\Models\Setting;

/**
 * Configuración de los ciclos de Pomodoro.
 *
 * El timer real corre 100% en el navegador (localStorage) — este service
 * solo expone las duraciones que el cliente lee al iniciar y persiste
 * lo que el usuario guarda en /settings/pomodoro.
 *
 * Sin métricas, sin streaks, sin nextTask: el Pomodoro es un timer puro,
 * desacoplado de tareas y proyectos.
 */
class PomodoroService
{
    /** Defaults razonables — clásicos de Pomodoro. */
    public const DEFAULTS = [
        'pomodoro_focus_min'         => 25,
        'pomodoro_short_break_min'   => 5,
        'pomodoro_long_break_min'    => 15,
        'pomodoro_cycles_until_long' => 4,
    ];

    /** Mínimos y máximos por campo, para clampear lo que llega del form. */
    private const RANGES = [
        'pomodoro_focus_min'         => [5,  120],
        'pomodoro_short_break_min'   => [1,  30],
        'pomodoro_long_break_min'    => [5,  60],
        'pomodoro_cycles_until_long' => [2,  10],
    ];

    /** Lee la config actual aplicando defaults para claves faltantes. */
    public function currentConfig(): array
    {
        return Setting::many(self::DEFAULTS);
    }

    /** Persiste un subset de claves (con clamp). Devuelve la config resultante. */
    public function saveConfig(array $values): array
    {
        foreach ($values as $key => $value) {
            if (! array_key_exists($key, self::DEFAULTS)) continue;
            [$min, $max] = self::RANGES[$key];
            $clamped     = max($min, min($max, (int) $value));
            Setting::set($key, $clamped);
        }
        return $this->currentConfig();
    }
}
