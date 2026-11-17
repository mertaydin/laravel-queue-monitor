<?php

namespace MertAydin\LaravelQueueMonitor\Tests\Feature;

use MertAydin\LaravelQueueMonitor\Tests\TestCase;

class ConfigurableRoutePathTest extends TestCase
{
    public function test_dashboard_path_supports_nested_paths_and_slash_normalization(): void
    {
        config([
            'queue-monitor.path' => '/panel/super/queue-monitor/',
            'queue-monitor.middleware' => ['web', 'super-admin', 'super-ip'],
        ]);

        require dirname(__DIR__, 2).'/routes/web.php';

        $route = collect(app('router')->getRoutes())->first(
            fn ($route): bool => $route->getName() === 'queue-monitor.index'
                && $route->uri() === 'panel/super/queue-monitor'
        );

        $this->assertNotNull($route);
        $this->assertContains('super-admin', $route->middleware());
        $this->assertContains('super-ip', $route->middleware());
    }
}
