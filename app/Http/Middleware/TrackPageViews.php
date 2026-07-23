<?php

namespace App\Http\Middleware;

use App\Models\PageView;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class TrackPageViews
{
    protected array $excludePrefixes = [
        '_debugbar',
        '_ignition',
        'telescope',
        'vendor',
        'storage',
        'hot',
        'build',
        '_ttinertia',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldTrack($request)) {
            $this->recordView($request);
        }

        return $response;
    }

    protected function shouldTrack(Request $request): bool
    {
        if (! $request->isMethod('GET')) {
            return false;
        }

        if ($request->ajax() || $request->expectsJson()) {
            return false;
        }

        $path = $request->path();

        foreach ($this->excludePrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return false;
            }
        }

        $route = $request->route();

        return $route !== null;
    }

    protected function recordView(Request $request): void
    {
        $user = $request->user();
        $url = $request->fullUrl();

        if ($user) {
            $cacheKey = "page_view:{$user->id}:".md5($url);
            if (Cache::has($cacheKey)) {
                return;
            }
            Cache::put($cacheKey, true, now()->addMinute());
        }

        PageView::create([
            'user_id' => $user?->id,
            'url' => $url,
            'route_name' => $request->route()->getName(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'visited_at' => now(),
        ]);
    }
}
