<?php

namespace App\Enums;

/**
 * Columna del tablero Kanban. Se persiste como string en `tasks.status`.
 * El orden de declaración es el orden de las columnas en el tablero.
 */
enum TaskStatus: string
{
    case Backlog = 'backlog';
    case Todo    = 'todo';
    case Doing   = 'doing';
    case Done    = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Backlog => 'Backlog',
            self::Todo    => 'Por hacer',
            self::Doing   => 'En curso',
            self::Done    => 'Hecho',
        };
    }
}
