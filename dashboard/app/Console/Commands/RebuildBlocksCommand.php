<?php

namespace App\Console\Commands;

use App\Services\Aggregator;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class RebuildBlocksCommand extends Command
{
    protected $signature = 'tracker:rebuild-blocks
                            {--since= : Inicio (cualquier formato strtotime) o "today", "yesterday", "N hours ago"}
                            {--until= : Fin (idem). Por defecto: now}
                            {--day=   : Atajo: reconstruye solo el dia indicado (YYYY-MM-DD)}
                            {--force-edited : Sobrescribe bloques editados manualmente}';

    protected $description = 'Re-agrega activity_events en time_blocks aplicando el scoring actual.';

    public function handle(Aggregator $aggregator): int
    {
        $tz = config('tracker.display_timezone', 'UTC');

        if ($day = $this->option('day')) {
            $localDay = CarbonImmutable::parse($day, $tz);
            $this->info("Rebuild dia local {$localDay->toDateString()} ({$tz})");
            $count = $aggregator->rebuildDay($localDay, $tz, (bool) $this->option('force-edited'));
            $this->info("→ {$count} bloques generados/actualizados");
            return self::SUCCESS;
        }

        $since = $this->option('since') ?? '24 hours ago';
        $until = $this->option('until') ?? 'now';

        // strtotime acepta "2 hours ago", "yesterday", etc. Lo parseamos en TZ local
        // y convertimos a UTC para el aggregator.
        $start = CarbonImmutable::parse($since, $tz)->setTimezone('UTC');
        $end   = CarbonImmutable::parse($until, $tz)->setTimezone('UTC');

        if ($end->lte($start)) {
            $this->error("Rango invalido: until ({$end}) debe ser > since ({$start})");
            return self::FAILURE;
        }

        $this->info("Rebuild rango UTC [{$start->toDateTimeString()} → {$end->toDateTimeString()})");
        $count = $aggregator->rebuildRange($start, $end, (bool) $this->option('force-edited'));
        $this->info("→ {$count} bloques generados/actualizados");

        return self::SUCCESS;
    }
}
