<?php

namespace App\Enums;

/**
 * Estado de un time_block. Se persiste como string en `time_blocks.status`
 * (cast del modelo TimeBlock).
 */
enum BlockStatus: string
{
    case Auto   = 'auto';
    case Edited = 'edited';
    case Merged = 'merged';
    case Split  = 'split';
    case Idle   = 'idle';

    /**
     * Estados que implican una intervención manual del usuario. El
     * Aggregator no los recalcula en los rebuilds salvo --force-edited.
     */
    public function isManual(): bool
    {
        return in_array($this, [self::Edited, self::Merged, self::Split], true);
    }
}
