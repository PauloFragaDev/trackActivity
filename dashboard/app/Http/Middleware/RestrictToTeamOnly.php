<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cuando APP_MODE=team_only (instancia pública del Kanban de equipo en el
 * VPS), bloquea con 404 cualquier ruta que no sea del Kanban, login/logout,
 * o el health check — no solo se esconde en la UI, es inalcanzable aunque
 * se sepa la URL exacta. En modo normal (local) no hace nada.
 */
class RestrictToTeamOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('app.mode') !== 'team_only') {
            return $next($request);
        }

        if ($request->is('/', 'team', 'team/*', 'login', 'logout', 'up')) {
            return $next($request);
        }

        abort(404);
    }
}
