<?php

namespace MertAydin\LaravelQueueMonitor\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class DashboardPreferenceController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'widgets' => ['sometimes', 'array', 'max:4'],
            'widgets.*' => ['string', 'in:analytics,workers,distribution,capabilities'],
            'refresh' => ['sometimes', 'integer', 'in:0,15,30,60'],
        ]);

        $widgets = array_values(array_unique($validated['widgets'] ?? []));
        $request->session()->put('queue-monitor.dashboard.widgets', $widgets);
        $request->session()->put('queue-monitor.dashboard.refresh', (int) ($validated['refresh'] ?? 0));

        return redirect()
            ->route('queue-monitor.index')
            ->with('status', 'Dashboard preferences saved.');
    }
}
