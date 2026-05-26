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

        $log = (string) config('tracker.log_file');
        @mkdir(dirname($log), 0775, true);

        // Entorno mínimo para que el collector de ventanas funcione bajo X11.
        $display    = getenv('DISPLAY') ?: ':0';
        $xauthority = getenv('XAUTHORITY') ?: ((getenv('HOME') ?: '/root') . '/.Xauthority');

        // nohup + redirecciones explícitas → el proceso sobrevive a la request.
        $cmd = sprintf(
            'nohup env DISPLAY=%s XAUTHORITY=%s %s run --foreground >> %s 2>&1 < /dev/null & echo $!',
            escapeshellarg($display),
            escapeshellarg($xauthority),
            escapeshellarg($bin),
            escapeshellarg($log),
        );

        $line = exec($cmd);
        $pid  = (int) trim((string) $line);
        if ($pid <= 0) {
            throw new RuntimeException('No se pudo arrancar el tracker (el shell no devolvió PID).');
        }

        $this->writePid($pid);
    }

    /** Detiene el daemon si está en marcha. */
    public function stop(): void
    {
        $status = $this->status();
        if (! $status['running']) {
            return;
        }

        if (function_exists('posix_kill')) {
            posix_kill($status['pid'], SIGTERM);
        } else {
            exec('kill ' . $status['pid'] . ' 2>/dev/null');
        }

        @unlink($this->pidFile());
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

    /**
     * Heurística para reconocer al daemon: bien el binario del venv, bien
     * `python -m tracker.cli run` (formato del unit de systemd).
     */
    private function cmdlineLooksLikeTracker(string $cmdline): bool
    {
        // /proc/.../cmdline separa argumentos con NUL.
        $flat = str_replace("\0", ' ', $cmdline);

        if (str_contains($flat, (string) config('tracker.bin')) && str_contains($flat, ' run')) {
            return true;
        }

        return str_contains($flat, 'tracker.cli') && str_contains($flat, ' run');
    }
}
