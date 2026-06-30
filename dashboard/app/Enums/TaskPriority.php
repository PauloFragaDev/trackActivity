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
            self::Low    => __('enum.priority_low'),
            self::Normal => __('enum.priority_normal'),
            self::High   => __('enum.priority_high'),
        };
    }
}
