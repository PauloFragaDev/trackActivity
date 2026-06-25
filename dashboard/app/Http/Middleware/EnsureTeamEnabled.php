<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTeamEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Setting::get('team.enabled', true)) {
            return redirect()->route('tasks.index');
        }

        return $next($request);
    }
}
