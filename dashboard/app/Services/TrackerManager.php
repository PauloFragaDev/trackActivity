<?php

namespace App\Services;

use RuntimeException;

/**
 * Controla el daemon Python del tracker desde el dashboard:
 * arrancar / detener / saber si está vivo.
 *
 * Estrategia: spawn directo con `nohup` y un PID file en `storage/`. Antes de
 * arrancar, escanea `/proc` por si ya hay un tracker corriendo (lanzado a mano
 * desde otra terminal) y lo adopta para no levantar una segunda instancia.
 */
class TrackerManager
{
    /**
     * @return array{running:bool,pid:?int}
     */
    public function status(): array
    {
        $pid = $this->readPid();
        if ($pid !== null && $this->isTrackerProcess($pid)) {
            return ['running' => true, 'pid' => $pid];
        }

        // PID file caducado o vacío: ¿hay un tracker huérfano vivo?
        if ($pid !== null) {
            @unlink($this->pidFile());
        }
        $orphan = $this->scanForTracker();
        if ($orphan !== null) {
            $this->writePid($orphan);
            return ['running' => true, 'pid' => $orphan];
        }

        return ['running' => false, 'pid' => null];
    }

    /** Arranca el daemon si no está ya en marcha. */
    public function start(): void
    {
        if ($this->status()['running']) {
            return;
        }

        $bin = (string) config('tracker.bin');
        if (! is_file($bin) || ! is_executable($bin)) {
            throw new RuntimeException("No se encuentra el ejecutable del tracker en {$bin}.");
        }

        $dir        = (string) config('tracker.dir');
        $configFile = (string) config('tracker.config_file');
        if (! is_file($configFile)) {
            throw new RuntimeException("No se encuentra el config del tracker en {$configFile}.");
        }

        $log = (string) config('tracker.log_file');
        @mkdir(dirname($log), 0775, true);
        $this->logMarker('inicio solicitado desde el dashboard');

        // Entorno mínimo para que el collector de ventanas funcione bajo X11.
        $display    = getenv('DISPLAY') ?: ':0';
        $xauthority = getenv('XAUTHORITY') ?: ((getenv('HOME') ?: '/root') . '/.Xauthority');

        // Pasamos --config explícito. El cwd lo cambiamos desde PHP (no con
        // `cd && …` en el shell) para que `$!` sea el PID del tracker en sí —
        // si se usa `cd && … &`, bash captura el PID del subshell y no del daemon.
        $cmd = sprintf(
            'nohup env DISPLAY=%s XAUTHORITY=%s %s run --foreground --config %s >> %s 2>&1 < /dev/null & echo $!',
            escapeshellarg($display),
            escapeshellarg($xauthority),
            escapeshellarg($bin),
            escapeshellarg($configFile),
            escapeshellarg($log),
        );

        $cwd = getcwd();
        if (! @chdir($dir)) {
            throw new RuntimeException("No se pudo acceder al directorio del tracker: {$dir}.");
        }
        try {
            $line = exec($cmd);
        } finally {
            if ($cwd !== false) {
                @chdir($cwd);
            }
        }
        $pid = (int) trim((string) $line);
        if ($pid <= 0) {
            $this->logMarker('arranque falló: el shell no devolvió PID');
            throw new RuntimeException('No se pudo arrancar el tracker (el shell no devolvió PID).');
        }

        // Damos al daemon medio segundo para arrancar y comprobamos que sigue vivo.
        // Si muere en ese tiempo, leemos el log y devolvemos una pista útil.
        usleep(600_000);
        if (! $this->isTrackerProcess($pid)) {
            $this->logMarker("arranque falló: el proceso murió tras spawn (pid={$pid})");
            $hint = $this->extractLogHint();
            @unlink($this->pidFile());
            throw new RuntimeException(trim(
                'El tracker arrancó pero se cayó. Revisa storage/logs/tracker.log.'
                . ($hint !== '' ? ' Último error: ' . $hint : '')
            ));
        }

        $this->writePid($pid);
        $this->logMarker("arrancado (pid={$pid})");
    }

    /** Detiene el daemon si está en marcha. */
    public function stop(): void
    {
        $status = $this->status();
        if (! $status['running']) {
            return;
        }
        $pid = $status['pid'];

        $this->logMarker("parada solicitada (pid={$pid})");
        $this->signal($pid, defined('SIGTERM') ? SIGTERM : 15);

        // Espera hasta ~3s a que termine limpiamente; si no, SIGKILL.
        for ($i = 0; $i < 30; $i++) {
            usleep(100_000);
            if (! $this->isTrackerProcess($pid)) {
                break;
            }
        }
        if ($this->isTrackerProcess($pid)) {
            $this->signal($pid, defined('SIGKILL') ? SIGKILL : 9);
            usleep(200_000);
            $this->logMarker("forzado con SIGKILL (pid={$pid})");
        }

        @unlink($this->pidFile());
    }

    private function signal(int $pid, int $signal): void
    {
        if (function_exists('posix_kill')) {
            posix_kill($pid, $signal);
            return;
        }
        $flag = $signal === 9 ? '-9 ' : '';
        exec("kill {$flag}{$pid} 2>/dev/null");
    }

    // ── Helpers ──────────────────────────────────────────────

    private function pidFile(): string
    {
        return (string) config('tracker.pid_file');
    }

    private function readPid(): ?int
    {
        if (! is_file($this->pidFile())) {
            return null;
        }
        $pid = (int) trim((string) @file_get_contents($this->pidFile()));

        return $pid > 0 ? $pid : null;
    }

    private function writePid(int $pid): void
    {
        @mkdir(dirname($this->pidFile()), 0775, true);
        file_put_contents($this->pidFile(), $pid);
    }

    /** ¿El PID corresponde a un proceso del tracker (no a un PID reciclado)? */
    private function isTrackerProcess(int $pid): bool
    {
        $cmdline = @file_get_contents("/proc/{$pid}/cmdline");
        if ($cmdline === false) {
            return false;
        }

        return $this->cmdlineLooksLikeTracker($cmdline);
    }

    /** Escanea /proc por un tracker en marcha que no esté en nuestro PID file. */
    private function scanForTracker(): ?int
    {
        foreach (glob('/proc/[0-9]*/cmdline') ?: [] as $file) {
            $cmdline = @file_get_contents($file);
            if ($cmdline === false) {
                continue;
            }
            if ($this->cmdlineLooksLikeTracker($cmdline)
                && preg_match('#/proc/(\d+)/cmdline$#', $file, $m)) {
                return (int) $m[1];
            }
        }

        return null;
    }

    /** Heurística para reconocer al daemon en /proc. */
    private function cmdlineLooksLikeTracker(string $cmdline): bool
    {
        // /proc/.../cmdline separa argumentos con NUL.
        $flat = str_replace("\0", ' ', $cmdline);
        $bin  = (string) config('tracker.bin');

        return $bin !== '' && str_contains($flat, $bin) && str_contains($flat, ' run');
    }

    /** Marca de tiempo en el log para trazar las acciones del dashboard. */
    private function logMarker(string $line): void
    {
        $log = (string) config('tracker.log_file');
        if ($log === '') {
            return;
        }
        @file_put_contents(
            $log,
            '[dashboard ' . date('Y-m-d H:i:s') . '] ' . $line . PHP_EOL,
            FILE_APPEND
        );
    }

    /** Extrae la última línea con pinta de error del log, para el flash. */
    private function extractLogHint(): string
    {
        $log = (string) config('tracker.log_file');
        if (! is_file($log)) {
            return '';
        }
        $tail = array_slice(explode("\n", rtrim((string) @file_get_contents($log))), -40);
        foreach (array_reverse($tail) as $line) {
            if (preg_match('/(Error|Exception|Traceback|not found|no encontrado)/i', $line)) {
                return mb_substr(trim(preg_replace('/\s+/', ' ', $line)), 0, 220);
            }
        }

        return mb_substr(trim((string) end($tail)), 0, 220);
    }
}
