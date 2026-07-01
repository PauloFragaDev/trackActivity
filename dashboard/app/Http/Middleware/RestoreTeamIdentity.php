<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Models\TeamMember;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * La sesión PHP puede perderse (expira, cookie de host distinto entre la app
 * de escritorio y el navegador, etc.) sin que el usuario haya pedido
 * desvincularse. `team.member_id` en Setting es la fuente persistente; esto
 * la rehidrata en sesión antes de que cualquier controlador de /team/* la lea,
 * en vez de duplicar el fallback controlador por controlador.
 */
class RestoreTeamIdentity
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! session('team_member_id')) {
            $savedId = Setting::get('team.member_id');
            if ($savedId) {
                try {
                    $member = TeamMember::find((int) $savedId);
                } catch (\Throwable $e) {
                    report($e);
                    $member = null;
                }

                if ($member) {
                    session([
                        'team_member_id'   => $member->id,
                        'team_member_name' => $member->name,
                    ]);
                }
            }
        }

        return $next($request);
    }
}
