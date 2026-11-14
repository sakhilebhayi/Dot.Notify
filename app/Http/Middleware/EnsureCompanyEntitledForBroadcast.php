<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * R-50: /broadcasting/auth is registered by the framework outside any route group of ours. This sits in the web
 * group and hands the one path to the entitlement check; every other request passes straight through.
 */
class EnsureCompanyEntitledForBroadcast
{
    public function __construct(private readonly EnsureCompanyEntitled $check) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('broadcasting/auth')) {
            return $next($request);
        }

        return $this->check->handle($request, $next);
    }
}
