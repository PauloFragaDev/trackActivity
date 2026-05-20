<?php

namespace App\Enums;

/**
 * Tipo de una entrada manual. Se persiste como string en
 * `manual_entries.kind` (cast del modelo ManualEntry).
 */
enum EntryKind: string
{
    case Meeting = 'meeting';
    case Focus   = 'focus';
    case Other   = 'other';

    /** Etiqueta legible para la UI. */
    public function label(): string
    {
        return match ($this) {
            self::Meeting => 'Reunión',
            self::Focus   => 'Trabajo',
            self::Other   => 'Otro',
        };
    }

    /** Color de acento (hex) para badges en la UI. */
    public function color(): string
    {
        return match ($this) {
            self::Meeting => '#a855f7',
            self::Focus   => '#0ea5e9',
            self::Other   => '#64748b',
        };
    }

    /** @return list<self> */
    public static function options(): array
    {
        return [self::Meeting, self::Focus, self::Other];
    }
}
