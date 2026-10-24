<?php

use App\Http\Middleware\InfrastructureHeaders;
use App\Mcp\InfrastructureServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

Route::middleware([InfrastructureHeaders::class, 'auth:sanctum', 'throttle:infrastructure'])->group(function (): void {
    Mcp::web('/api/mcp', InfrastructureServer::class)->name('infra.mcp');
});
