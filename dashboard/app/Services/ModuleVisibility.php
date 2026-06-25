<?php

namespace App\Services;

use App\Models\Setting;

/**
 * Catálogo de módulos opcionales de la app y su estado de visibilidad.
 *
 * El Tracker (timeline / dashboard) es núcleo y no se puede ocultar. Para
 * el resto el usuario decide qué quiere ver en el sidebar global y en
 * navegación periférica (quick switcher). Las preferencias se persisten
 * en `settings` (key = "modules.<slug>", value = bool).
 *
 * Patrón single-user (igual que Pomodoro): no hay user_id.
 */
class ModuleVisibility
{
    /**
     * Catálogo. El orden aquí define el orden en la página de ajustes.
     *
     * @var array<string, array{label:string,description:string,icon:string,required?:bool}>
     */
    public const MODULES = [
        'notes' => [
            'label'       => 'Notas',
            'description' => 'Carpetas, papelera y editor markdown. Si lo desactivas se ocultan también los favoritos del sidebar.',
            'icon'        => 'note',
        ],
        'tasks' => [
            'label'       => 'Tareas (Kanban)',
            'description' => 'Tablero, etiquetas, subtareas y sincronización con code-kanban.',
            'icon'        => 'kanban',
        ],
        'pomodoro' => [
            'label'       => 'Pomodoro',
            'description' => 'Temporizador focus/pausa y meta diaria. La sección "Pomodoro" en ajustes desaparece si lo desactivas.',
            'icon'        => 'clock',
        ],
        'calendar' => [
            'label'       => 'Calendario',
            'description' => 'Vista mensual de actividad agregada.',
            'icon'        => 'calendar',
        ],
        'reports' => [
            'label'       => 'Informes',
            'description' => 'Resúmenes por proyecto y rangos personalizados.',
            'icon'        => 'chart',
        ],
        'insights' => [
            'label'       => 'Insights',
            'description' => 'Resumen automático del día/semana, foco vs fragmentación, inactividad y tendencias por proyecto.',
            'icon'        => 'sparkles',
        ],
        'team' => [
            'label'       => 'Kanban de equipo',
            'description' => 'Tablero compartido vía Supabase para trabajar en equipo. Desactívalo si no usas Supabase en esta instalación.',
            'icon'        => 'bars',
        ],
    ];

    /**
     * ¿Está activado un módulo? Por defecto sí (estamos opt-out, no opt-in).
     */
    public static function enabled(string $module): bool
    {
        if (! isset(self::MODULES[$module])) return false;
        return (bool) Setting::get('modules.' . $module, true);
    }

    /**
     * Lista de todos los módulos con su estado actual.
     * Útil para la vista de ajustes y el sidebar global.
     *
     * @return array<string, array{label:string,description:string,icon:string,enabled:bool}>
     */
    public static function all(): array
    {
        $defaults = [];
        foreach (array_keys(self::MODULES) as $slug) {
            $defaults['modules.' . $slug] = true;
        }
        $values = Setting::many($defaults);

        $out = [];
        foreach (self::MODULES as $slug => $meta) {
            $out[$slug] = $meta + ['enabled' => (bool) $values['modules.' . $slug]];
        }
        return $out;
    }

    /**
     * Guarda en bloque el estado de los toggles. Acepta el array tal cual
     * lo envía el form (`['modules' => ['notes' => '1', ...]]`).
     *
     * Las claves desconocidas se ignoran. Una clave ausente significa
     * desactivada (los checkboxes no envían valor cuando no marcados).
     */
    public static function saveAll(array $submitted): void
    {
        foreach (array_keys(self::MODULES) as $slug) {
            Setting::set('modules.' . $slug, isset($submitted[$slug]));
        }
    }
}
