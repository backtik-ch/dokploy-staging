<?php

use App\Http\Controllers\DependencyReportController;
use App\Http\Controllers\DeployController;
use Illuminate\Support\Facades\Route;

Route::post('/deploy', [DeployController::class, 'deploy'])->middleware('auth:sanctum');
Route::delete('/remove', [DeployController::class, 'delete'])->middleware('auth:sanctum');
Route::post('/dependencies', [DependencyReportController::class, 'store'])->middleware(['auth:sanctum', 'throttle:60,1']);
