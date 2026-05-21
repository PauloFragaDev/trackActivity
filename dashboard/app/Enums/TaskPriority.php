<?php

namespace App\Enums;

/**
 * Prioridad de una tarea. Se persiste como string en `tasks.priority`.
 */
enum TaskPriority: string
{
    case Low    = 'low';
    case Normal = 'normal';
    case High   = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Low    => 'Baja',
            self::Normal => 'Normal',
            self::High   => 'Alta',
        };
    }
}
