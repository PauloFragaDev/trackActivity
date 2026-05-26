<?php

namespace App\Services;

use RuntimeException;

/**
 * Controla el scheduler de Laravel (`php artisan schedule:work`), el proceso
 * que dispara `tracker:rebuild-blocks`, `tracker:generate-summaries`,
 * `backup:snapshot` y `tasks:sync` a sus cadencias.
 *
 * Mismo patrón que {@see TrackerManager}: spawn con nohup, PID file en
 * storage/, detección de instancias huérfanas vía /proc.
 */
class SchedulerManager
{
    /**
     * @return array{running:bool,pid:?int}
     */
    public function status(): array
    {
        $pid = $this->readPid();
        if ($pid !== null && $this->isSchedulerProcess($pid)) {
            return ['running' => true, 'pid' => $pid];
        }
        if ($pid !== null) {
            @unlink($this->pidFile());
        }
        $orphan = $this->scanForScheduler();
        if ($orphan !== null) {
            $this->writePid($orphan);
            return ['running' => true, 'pid' => $orphan];
        }

        return ['running' => false, 'pid' => null];
    }

    public function start(): void
    {
        if ($this->status()['running']) {
            return;
        }

        $cwd = base_path();   // donde vive `artisan`
        $log = (string) config('tracker.scheduler.log_file');
        @mkdir(dirname($log), 0775, true);
        $this->logMarker('inicio solicitado');

        // PHP_BINARY: misma versión de PHP que sirve la request.
        $cmd = sprintf(
            'nohup %s artisan schedule:work >> %s 2>&1 < /dev/null & echo $!',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($log),
        );

        $prev = getcwd();
        if (! @chdir($cwd)) {
            throw new RuntimeException("No se pudo acceder al directorio de la app: {$cwd}.");
        }
        try {
            $line = exec($cmd);
        } finally {
            if ($prev !== false) {
                @chdir($prev);
            }
        }
        $pid = (int) trim((string) $line);
        if ($pid <= 0) {
            $this->logMarker('arranque falló: el shell no devolvió PID');
            throw new RuntimeException('No se pudo arrancar el scheduler.');
        }

        usleep(600_000);
        if (! $this->isSchedulerProcess($pid)) {
            $this->logMarker("arranque falló: el proceso murió tras spawn (pid={$pid})");
            @unlink($this->pidFile());
            throw new RuntimeException(
                'El scheduler arrancó pero se cayó. Revisa storage/logs/scheduler.log.'
            );
        }

        $this->writePid($pid);
        $this->logMarker("arrancado (pid={$pid})");
    }

    public function stop(): void
    {
        $status = $this->status();
        if (! $status['running']) {
            return;
        }
        $pid = $status['pid'];

        $this->logMarker("parada solicitada (pid={$pid})");
        $this->signal($pid, defined('SIGTERM') ? SIGTERM : 15);

        for ($i = 0; $i < 30; $i++) {
            usleep(100_000);
            if (! $this->isSchedulerProcess($pid)) {
                break;
            }
        }
        if ($this->isSchedulerProcess($pid)) {
            $this->signal($pid, defined('SIGKILL') ? SIGKILL : 9);
            usleep(200_000);
            $this->logMarker("forzado con SIGKILL (pid={$pid})");
        }

        @unlink($this->pidFile());
    }

    // ── Helpers ──────────────────────────────────────────────

    private function pidFile(): string
    {
        return (string) config('tracker.scheduler.pid_file');
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

    private function isSchedulerProcess(int $pid): bool
    {
        $cmdline = @file_get_contents("/proc/{$pid}/cmdline");
        if ($cmdline === false) {
            return false;
        }

        return $this->cmdlineLooksLikeScheduler($cmdline);
    }

    private function scanForScheduler(): ?int
    {
        foreach (glob('/proc/[0-9]*/cmdline') ?: [] as $file) {
            $cmdline = @file_get_contents($file);
            if ($cmdline === false) {
                continue;
            }
            if ($this->cmdlineLooksLikeScheduler($cmdline)
                && preg_match('#/proc/(\d+)/cmdline$#', $file, $m)) {
                return (int) $m[1];
            }
        }

        return null;
    }

    /**
     * Identifica al scheduler por la ESTRUCTURA de su cmdline:
     *   argv[0] = .../php
     *   argv[1] = .../artisan
     *   argv[2] = schedule:work
     * Esto evita falsos positivos (p.ej. shells con `pkill -f 'schedule:work'`).
     */
    private function cmdlineLooksLikeScheduler(string $cmdline): bool
    {
        $identifier = (string) config('tracker.scheduler.identifier');
        if ($identifier === '') {
            return false;
        }

        $args = explode("\0", rtrim($cmdline, "\0"));
        if (count($args) < 3) {
            return false;
        }

        $php     = basename($args[0]);
        $artisan = basename($args[1]);

        return str_starts_with($php, 'php')
            && $artisan === 'artisan'
            && $args[2] === $identifier;
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

    private function logMarker(string $line): void
    {
        $log = (string) config('tracker.scheduler.log_file');
        if ($log === '') {
            return;
        }
        @file_put_contents(
            $log,
            '[dashboard ' . date('Y-m-d H:i:s') . '] ' . $line . PHP_EOL,
            FILE_APPEND
        );
    }
}
