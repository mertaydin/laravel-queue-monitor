<?php

namespace MertAydin\LaravelQueueMonitor\Services;

use Closure;
use Illuminate\Http\Request;

class DashboardAuthorizer
{
    private ?Closure $callback = null;

    public function auth(callable $callback): void
    {
        $this->callback = Closure::fromCallable($callback);
    }

    public function allows(Request $request): bool
    {
        // Queue actions can retry, delete, or control workers. Never expose the
        // dashboard unless the host application explicitly authorizes the request.
        return $this->callback !== null && (bool) ($this->callback)($request);
    }
}
