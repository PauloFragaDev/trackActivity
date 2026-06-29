<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\SchedulerManager;
use App\Services\TrackerManager;
use Illuminate\Console\Command;

class TrackerAutostartCommand extends Command
{
    protected $signature = 'tracker:autostart';
    protected $description = 'Arranca el tracker solo si tracking.enabled está activo en Settings';

    public function handle(TrackerManager $tracker, SchedulerManager $scheduler): int
    {
        if (! Setting::get('tracking.enabled', false)) {
            return 0;
        }

        $tracker->start();
        $scheduler->start();
        return 0;
    }
}
