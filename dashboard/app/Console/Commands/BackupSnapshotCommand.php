<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

/**
 * Crea una copia de seguridad de la base de datos y poda las antiguas.
 * Se ejecuta a diario desde el scheduler (routes/console.php).
 */
class BackupSnapshotCommand extends Command
{
    protected $signature = 'backup:snapshot {--keep=14 : Número de copias a conservar}';

    protected $description = 'Crea una copia de seguridad de la base de datos y poda las antiguas.';

    public function handle(BackupService $backups): int
    {
        try {
            $path = $backups->create();
        } catch (\Throwable $e) {
            $this->error('No se pudo crear la copia: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info('Copia creada: ' . $path);

        $backups->prune(max(1, (int) $this->option('keep')));

        return self::SUCCESS;
    }
}
