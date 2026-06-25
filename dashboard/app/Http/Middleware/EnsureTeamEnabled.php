<?php

namespace App\Http\Middleware;

use App\Services\ModuleVisibility;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTeamEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!ModuleVisibility::enabled('team')) {
            return redirect()->route('tasks.index');
        }

        return $next($request);
    }
}
