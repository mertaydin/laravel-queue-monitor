<?php

namespace MertAydin\LaravelQueueMonitor\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class AuthorizeDashboard
{
    public function handle(Request $request, Closure $next): Response
    {
        $ability = (string) config('queue-monitor.authorization.gate', 'viewQueueMonitor');

        if (Gate::has($ability)) {
            Gate::authorize($ability);
        }

        return $next($request);
    }
}
