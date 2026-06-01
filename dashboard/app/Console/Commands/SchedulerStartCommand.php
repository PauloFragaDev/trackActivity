<?php

namespace App\Console\Commands;

use App\Services\SchedulerManager;
use Illuminate\Console\Command;

class SchedulerStartCommand extends Command
{
    protected $signature = 'scheduler:start';
    protected $description = 'Arranca el scheduler (reusa SchedulerManager).';

    public function handle(SchedulerManager $scheduler): int
    {
        if ($scheduler->status()['running']) {
            $this->info('El scheduler ya estaba en marcha.');
            return self::SUCCESS;
        }
        try {
            $scheduler->start();
        } catch (\Throwable $e) {
            $this->error('No se pudo arrancar el scheduler: ' . $e->getMessage());
            return self::FAILURE;
        }
        $this->info('Scheduler arrancado.');
        return self::SUCCESS;
    }
}
