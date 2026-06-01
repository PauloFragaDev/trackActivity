<?php

namespace App\Console\Commands;

use App\Services\SchedulerManager;
use Illuminate\Console\Command;

class SchedulerStopCommand extends Command
{
    protected $signature = 'scheduler:stop';
    protected $description = 'Para el scheduler (reusa SchedulerManager).';

    public function handle(SchedulerManager $scheduler): int
    {
        try {
            $scheduler->stop();
        } catch (\Throwable $e) {
            $this->error('No se pudo parar el scheduler: ' . $e->getMessage());
            return self::FAILURE;
        }
        $this->info('Scheduler parado.');
        return self::SUCCESS;
    }
}
