<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = Setting::get('app.locale', 'es');
        if (in_array($locale, ['es', 'ca'])) {
            app()->setLocale($locale);
        }
        return $next($request);
    }
}
