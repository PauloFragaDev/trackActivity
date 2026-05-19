<?php

namespace App\Console\Commands;

use App\Models\ActivityEvent;
use App\Models\TimeBlock;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneEventsCommand extends Command
{
    protected $signature = 'tracker:prune-events
                            {--older-than=90 days : Edad minima de los events a borrar (strtotime)}
                            {--keep-blocks       : Si se pasa, no borra time_blocks huerfanos}
                            {--dry-run           : Solo cuenta cuanto borraria}';

    protected $description = 'Borra activity_events antiguos para mantener la BBDD manejable. time_block_evidence sigue via cascade.';

    public function handle(): int
    {
        $age = (string) $this->option('older-than');
        $cutoff = CarbonImmutable::parse("-{$age}", 'UTC');

        $count = ActivityEvent::query()
            ->where('occurred_at', '<', $cutoff->format('Y-m-d H:i:s'))
            ->count();

        $this->info("Cutoff UTC: {$cutoff->toDateTimeString()}");
        $this->info("→ Eventos candidatos a borrar: {$count}");

        if ($count === 0) {
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn('--dry-run: no se borra nada');
            return self::SUCCESS;
        }

        // Borrado por chunks para evitar transacciones gigantes
        $totalDeleted = 0;
        do {
            $deleted = DB::transaction(function () use ($cutoff) {
                return ActivityEvent::query()
                    ->where('occurred_at', '<', $cutoff->format('Y-m-d H:i:s'))
                    ->limit(1000)
                    ->delete();
            });
            $totalDeleted += $deleted;
            if ($deleted > 0) {
                $this->getOutput()->write('.');
            }
        } while ($deleted > 0);
        $this->newLine();
        $this->info("→ Borrados: {$totalDeleted} events");

        // Limpieza de time_blocks que ya no tienen evidencia (huerfanos)
        if (! $this->option('keep-blocks')) {
            $orphanIds = TimeBlock::query()
                ->where('starts_at', '<', $cutoff->format('Y-m-d H:i:s'))
                ->doesntHave('evidence')
                ->pluck('id');
            $orphanCount = $orphanIds->count();
            if ($orphanCount > 0) {
                TimeBlock::query()->whereIn('id', $orphanIds)->delete();
                $this->info("→ Borrados {$orphanCount} time_blocks huerfanos");
            }
        }

        return self::SUCCESS;
    }
}
