<?php

namespace App\Enums;

/**
 * Columna del tablero Kanban. Se persiste como string en `tasks.status`.
 *
 * El orden de declaración es el orden visual de las columnas en el tablero,
 * y coincide con el set fijo que usa la extensión code-kanban (un kanban por
 * repo, mismas columnas en ambos lados):
 *
 *   Blocked → Backlog → To Do → Doing → Stand By → Done
 *
 * Los labels se mantienen en inglés para que ambos productos hablen el
 * mismo idioma de columnas sin necesidad de traducción.
 */
enum TaskStatus: string
{
    case Blocked = 'blocked';
    case Backlog = 'backlog';
    case Todo    = 'todo';
    case Doing   = 'doing';
    case StandBy = 'standby';
    case Done    = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Blocked => 'Blocked',
            self::Backlog => 'Backlog',
            self::Todo    => 'To Do',
            self::Doing   => 'Doing',
            self::StandBy => 'Stand By',
            self::Done    => 'Done',
        };
    }

    /** Estados "trabajables" — los que el Pomodoro puede sugerir. */
    public function isActionable(): bool
    {
        return match ($this) {
            self::Doing, self::Todo, self::Backlog => true,
            self::Blocked, self::StandBy, self::Done => false,
        };
    }
}
