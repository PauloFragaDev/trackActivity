<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Copias de seguridad de la base de datos SQLite.
 *
 * Las copias se hacen con `VACUUM INTO`, que produce un fichero consistente
 * aunque el daemon esté escribiendo a la vez. Viven en `storage/backups/`,
 * hermana del propio fichero de la BBDD.
 */
class BackupService
{
    /** Ruta absoluta del fichero SQLite. */
    public function databasePath(): string
    {
        return (string) config('database.connections.sqlite.database');
    }

    /** Carpeta de copias; se crea si no existe. */
    public function directory(): string
    {
        $dir = dirname($this->databasePath()) . '/backups';

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    /** Crea una copia consistente de la BBDD y devuelve su ruta. */
    public function create(string $prefix = 'activity'): string
    {
        $dest = $this->directory() . '/' . $prefix . '-' . now()->format('Y-m-d-His') . '.db';

        // VACUUM INTO: copia consistente aunque haya escrituras concurrentes.
        DB::statement("VACUUM INTO '" . str_replace("'", "''", $dest) . "'");

        return $dest;
    }

    /** Conserva solo las $keep copias más recientes; borra el resto. */
    public function prune(int $keep = 14): void
    {
        foreach (array_slice($this->snapshots(), max(1, $keep)) as $old) {
            @unlink($old['path']);
        }
    }

    /**
     * Copias existentes, de la más reciente a la más antigua.
     *
     * @return list<array{name:string,path:string,size:int,mtime:int}>
     */
    public function snapshots(): array
    {
        $list = [];

        foreach (glob($this->directory() . '/*.db') ?: [] as $file) {
            $list[] = [
                'name'  => basename($file),
                'path'  => $file,
                'size'  => (int) filesize($file),
                'mtime' => (int) filemtime($file),
            ];
        }

        usort($list, fn ($a, $b) => $b['mtime'] <=> $a['mtime']);

        return $list;
    }

    /** Devuelve la ruta de una copia por su nombre, o null si no existe. */
    public function snapshotPath(string $name): ?string
    {
        $path = $this->directory() . '/' . basename($name);   // basename: evita path traversal

        return is_file($path) ? $path : null;
    }

    /** ¿Es $path un fichero SQLite válido? (comprueba la cabecera) */
    public function isSqliteFile(string $path): bool
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }

        $header = fread($handle, 16);
        fclose($handle);

        return $header === "SQLite format 3\000";
    }

    /**
     * Restaura la BBDD desde $sourcePath. Antes guarda una copia del estado
     * actual (prefijo `pre-restore`) por si hay que deshacer.
     */
    public function restore(string $sourcePath): void
    {
        if (! $this->isSqliteFile($sourcePath)) {
            throw new RuntimeException('El fichero no es una base de datos SQLite válida.');
        }

        // Red de seguridad: copia del estado actual antes de sobrescribir.
        $this->create('pre-restore');

        // Swap atómico: copiar a un temporal junto al destino y renombrar.
        $db  = $this->databasePath();
        $tmp = $db . '.restoring';

        if (! copy($sourcePath, $tmp)) {
            throw new RuntimeException('No se pudo preparar la restauración.');
        }

        rename($tmp, $db);
    }
}
