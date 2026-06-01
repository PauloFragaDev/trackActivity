<?php

namespace App\Console\Commands;

use App\Services\TrackerManager;
use Illuminate\Console\Command;

class TrackerStopCommand extends Command
{
    protected $signature = 'tracker:stop';
    protected $description = 'Para el daemon del tracker (reusa TrackerManager).';

    public function handle(TrackerManager $tracker): int
    {
        try {
            $tracker->stop();
        } catch (\Throwable $e) {
            $this->error('No se pudo parar el tracker: ' . $e->getMessage());
            return self::FAILURE;
        }
        $this->info('Tracker parado.');
        return self::SUCCESS;
    }
}
