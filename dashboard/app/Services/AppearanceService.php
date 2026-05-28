<?php

namespace App\Services;

use App\Models\Setting;

/**
 * Catálogo de temas y persistencia del tema activo.
 *
 * Cada tema redefine la paleta semántica (--paper, --ink-*, --accent…) en
 * sus dos variantes — el toggle claro/oscuro sigue siendo ortogonal y
 * alterna .dark dentro del mismo tema.
 *
 *   default → la paleta original de la app (slate + accent verde).
 *   paper   → "papel/bambú" de floral-notepaper (cremas cálidos).
 *   notion  → neutros cálidos al estilo tolaria/Notion + accent azul.
 *   mono    → grises puros para los puristas.
 *
 * El catálogo aquí lleva un swatch (3 colores light) y un name para la
 * grid de selección; los colores reales viven en CSS, en bloques
 * :root[data-theme="<id>"] de resources/css/app.css.
 */
class AppearanceService
{
    public const SETTING_KEY = 'theme.id';
    public const DEFAULT_ID  = 'default';

    /**
     * @var array<string, array{label:string,description:string,swatch:array{paper:string,ink:string,accent:string}}>
     */
    public const THEMES = [
        'default' => [
            'label'       => 'Default',
            'description' => 'La paleta original de la app: slate + acento verde.',
            'swatch'      => ['paper' => '#ffffff', 'ink' => '#0f172a', 'accent' => '#10b981'],
        ],
        'paper' => [
            'label'       => 'Paper',
            'description' => 'Cremas cálidos y acento bambú — inspirado en floral-notepaper.',
            'swatch'      => ['paper' => '#f6f3ec', 'ink' => '#1a1a18', 'accent' => '#2d5a3d'],
        ],
        'notion' => [
            'label'       => 'Notion',
            'description' => 'Neutros cálidos y acento azul Notion — inspirado en tolaria.',
            'swatch'      => ['paper' => '#ffffff', 'ink' => '#37352f', 'accent' => '#155dff'],
        ],
        'mono' => [
            'label'       => 'Mono',
            'description' => 'Grises puros, sin acento de color. Para minimalistas.',
            'swatch'      => ['paper' => '#fafafa', 'ink' => '#171717', 'accent' => '#525252'],
        ],
    ];

    public static function current(): string
    {
        $id = (string) Setting::get(self::SETTING_KEY, self::DEFAULT_ID);
        return isset(self::THEMES[$id]) ? $id : self::DEFAULT_ID;
    }

    public static function setCurrent(string $id): void
    {
        if (! isset(self::THEMES[$id])) $id = self::DEFAULT_ID;
        Setting::set(self::SETTING_KEY, $id);
    }
}
