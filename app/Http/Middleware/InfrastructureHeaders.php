<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InfrastructureHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // MCP uses bearer authentication. Refuse browser requests from a different origin.
        if ($request->is('api/mcp') && $request->hasHeader('Origin')) {
            abort_unless(rtrim($request->header('Origin'), '/') === $request->getSchemeAndHttpHost(), 403, 'Origin refusée.');
        }
        $request->headers->set('Accept', 'application/json');

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
