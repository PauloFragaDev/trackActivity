<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Task;
use App\Services\CodeKanban\KanbanSyncService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * API REST · canal SSE para que la extensión code-kanban (fork) reciba
 * notificaciones en vivo cuando algo cambia en el proyecto enlazado a un
 * workspace.
 *
 * Estrategia: la respuesta es un text/event-stream. Dentro, hacemos
 * polling cada 1 s al MAX(updated_at) de las tasks del proyecto. Si el
 * valor cambia, emitimos un evento `change`. Heartbeat cada 15 s para
 * mantener vivos NAT/proxies. Rotamos la conexión cada 60 s; el cliente
 * se reconecta solo (la API del fetch streaming hace backoff).
 *
 * Garantías:
 *   - Si el cliente se desconecta, `connection_aborted()` termina el loop
 *     y libera el worker en menos de 1 s.
 *   - Sin token: el middleware `api.token` corta antes de entrar aquí.
 *   - Sin mapping del workspace: 422 inmediato (no stream).
 *
 * Limitación operativa: cada conexión SSE ocupa un worker del servidor
 * PHP. Con `PHP_CLI_SERVER_WORKERS=4` y 1-2 workspaces abiertos es
 * cómodo; con más habría que servir tras nginx/php-fpm.
 */
class KanbanStreamController extends Controller
{
    /** Duración máxima de una conexión antes de pedir reconexión. */
    private const ROTATE_AFTER_SECONDS = 60;

    /** Periodo del polling interno a la BBDD (segundos). */
    private const POLL_INTERVAL_SECONDS = 1;

    /** Heartbeat (comment SSE) para keepalive. */
    private const HEARTBEAT_EVERY_SECONDS = 15;

    public function __construct(private readonly KanbanSyncService $service) {}

    public function stream(Request $request): StreamedResponse
    {
        abort_if(
            ! Setting::get('sync.extension', true),
            403,
            'La sincronización con la extensión está desactivada en Configuración.'
        );

        $data = $request->validate([
            'workspace_path' => ['required', 'string', 'max:1024'],
        ]);

        $project = $this->service->resolveProject($data['workspace_path']);
        if (! $project) {
            // Devolvemos 422 con el mismo formato que /api/sync/kanban para
            // que el cliente pueda distinguir y mostrar el mismo mensaje.
            return new StreamedResponse(
                fn () => print json_encode([
                    'error'   => 'no_project_mapping',
                    'message' => "No hay ProjectMapping (type folder/repository) para «{$data['workspace_path']}».",
                ]),
                422,
                ['Content-Type' => 'application/json'],
            );
        }

        $projectId = $project->id;

        return new StreamedResponse(function () use ($projectId) {
            // Sin límite — la rotación de 60 s se gestiona desde el loop.
            @set_time_limit(0);
            ignore_user_abort(false);

            // Para que cada print salga del buffer y llegue al cliente.
            while (ob_get_level() > 0) {
                @ob_end_flush();
            }

            $startedAt = time();
            $nextHeartbeat = $startedAt + self::HEARTBEAT_EVERY_SECONDS;

            $latest = $this->latestUpdatedAt($projectId);

            // Saludo inicial — confirmación de canal abierto para el cliente.
            $this->emit('hello', [
                'project_id'           => $projectId,
                'latest'               => $latest,
                'rotate_after_seconds' => self::ROTATE_AFTER_SECONDS,
            ]);

            while (true) {
                if (connection_aborted()) {
                    break;
                }
                if (time() - $startedAt >= self::ROTATE_AFTER_SECONDS) {
                    // Salimos limpiamente para que el cliente reconecte y no
                    // mantengamos workers eternos.
                    $this->emit('rotate', ['reason' => 'max_duration']);
                    break;
                }

                $current = $this->latestUpdatedAt($projectId);
                if ($current !== null && $current !== $latest) {
                    $this->emit('change', [
                        'project_id' => $projectId,
                        'latest'     => $current,
                    ]);
                    $latest = $current;
                    $nextHeartbeat = time() + self::HEARTBEAT_EVERY_SECONDS;
                } elseif (time() >= $nextHeartbeat) {
                    // Comentario SSE (línea ":") como keepalive.
                    echo ": heartbeat\n\n";
                    @flush();
                    $nextHeartbeat = time() + self::HEARTBEAT_EVERY_SECONDS;
                }

                sleep(self::POLL_INTERVAL_SECONDS);
            }
        }, 200, [
            'Content-Type'      => 'text/event-stream; charset=utf-8',
            'Cache-Control'     => 'no-cache, no-transform',
            'Connection'        => 'keep-alive',
            // Si está detrás de nginx, esto le pide que no buferee.
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /** Último updated_at de las tasks del proyecto (incluye soft-deleted). */
    private function latestUpdatedAt(int $projectId): ?string
    {
        $value = Task::withTrashed()
            ->where('project_id', $projectId)
            ->max('updated_at');
        return $value ? (string) $value : null;
    }

    /** Emite un evento SSE con nombre y payload JSON. */
    private function emit(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
        @flush();
    }
}
