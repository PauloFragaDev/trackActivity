<?php

namespace App\Console\Commands;

use App\Services\TrackerManager;
use Illuminate\Console\Command;

class TrackerStartCommand extends Command
{
    protected $signature = 'tracker:start';
    protected $description = 'Arranca el daemon del tracker (reusa TrackerManager).';

    public function handle(TrackerManager $tracker): int
    {
        if ($tracker->status()['running']) {
            $this->info('El tracker ya estaba en marcha.');
            return self::SUCCESS;
        }
        try {
            $tracker->start();
        } catch (\Throwable $e) {
            $this->error('No se pudo arrancar el tracker: ' . $e->getMessage());
            return self::FAILURE;
        }
        $this->info('Tracker arrancado.');
        return self::SUCCESS;
    }
}
