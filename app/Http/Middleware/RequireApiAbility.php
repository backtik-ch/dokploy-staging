<?php

namespace App\Http\Middleware;

use App\Services\Infrastructure\ApiAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireApiAbility
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        ApiAccess::authorize($request->user(), $ability);

        return $next($request);
    }
}
