<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bearer token estático para la API REST single-user.
 *
 * El token se configura en .env (`API_TOKEN`) y se lee de
 * `config('app.api_token')`. Si la app no tiene token configurado, la
 * API responde 503 — explícito mejor que un 401 silencioso que invita
 * a forzar contraseñas.
 *
 * Comparación con `hash_equals` para resistir timing attacks.
 */
class ApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('app.api_token', '');

        if ($expected === '') {
            return response()->json([
                'error' => 'API_TOKEN no configurado en el servidor.',
            ], 503);
        }

        $provided = (string) ($request->bearerToken() ?? '');

        if ($provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }
}
