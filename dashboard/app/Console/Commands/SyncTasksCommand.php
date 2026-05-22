<?php

namespace App\Console\Commands;

use App\Services\GitHub\ProjectClient;
use App\Services\GitHub\TaskSyncService;
use Illuminate\Console\Command;

/**
 * Sincroniza el tablero de tareas con el GitHub Project configurado.
 */
class SyncTasksCommand extends Command
{
    protected $signature = 'tasks:sync';

    protected $description = 'Sincroniza el tablero de tareas con el GitHub Project.';

    public function handle(ProjectClient $client, TaskSyncService $sync): int
    {
        if (! $client->isConfigured()) {
            $this->warn('Sincronización con GitHub no configurada (GITHUB_TOKEN / GITHUB_PROJECT).');
            return self::SUCCESS;
        }

        try {
            $result = $sync->sync();
        } catch (\Throwable $e) {
            $this->error('La sincronización falló: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info("Sincronización correcta — subidas: {$result['pushed']}, "
            . "creadas: {$result['created']}, actualizadas: {$result['updated']}, "
            . "eliminadas: {$result['removed']}.");

        return self::SUCCESS;
    }
}
