<?php

use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\CapabilityController;
use App\Http\Controllers\Api\ConnectionController;
use App\Http\Controllers\Api\DependencyController;
use App\Http\Controllers\Api\LogController;
use App\Http\Controllers\Api\MaintenanceController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectSettingController;
use App\Http\Controllers\Api\StagingController;
use App\Http\Controllers\Api\TokenController;
use App\Http\Controllers\DependencyReportController;
use App\Http\Controllers\DeployController;
use App\Http\Middleware\InfrastructureHeaders;
use App\Http\Middleware\RequireApiAbility;
use Illuminate\Support\Facades\Route;

Route::post('/deploy', [DeployController::class, 'deploy'])->middleware(['auth:sanctum', RequireApiAbility::class.':stagings:deploy']);
Route::delete('/remove', [DeployController::class, 'delete'])->middleware(['auth:sanctum', RequireApiAbility::class.':stagings:delete']);
Route::post('/dependencies', [DependencyReportController::class, 'store'])->middleware(['auth:sanctum', RequireApiAbility::class.':dependencies:write', 'throttle:60,1']);

Route::prefix('v1')->name('infra.')->middleware([InfrastructureHeaders::class, 'auth:sanctum', 'throttle:infrastructure'])->group(function (): void {
    Route::get('/capabilities', [CapabilityController::class, 'index'])->name('capabilities');
    Route::get('/openapi.json', [CapabilityController::class, 'openapi'])->name('openapi');

    Route::apiResource('projects', ProjectController::class)->parameters(['projects' => 'id']);
    Route::apiResource('connections', ConnectionController::class)->parameters(['connections' => 'id']);
    Route::apiResource('tokens', TokenController::class)->parameters(['tokens' => 'id']);
    Route::post('/tokens/{id}/rotate', [TokenController::class, 'rotate'])->name('tokens.rotate');

    Route::get('/stagings', [StagingController::class, 'index'])->name('stagings.index');
    Route::post('/projects/{project_id}/stagings', [StagingController::class, 'store'])->name('stagings.store');
    Route::get('/stagings/{id}', [StagingController::class, 'show'])->name('stagings.show');
    Route::patch('/stagings/{id}', [StagingController::class, 'update'])->name('stagings.update');
    Route::delete('/stagings/{id}', [StagingController::class, 'destroy'])->name('stagings.destroy');
    Route::post('/stagings/{id}/redeploy', [StagingController::class, 'redeploy'])->name('stagings.redeploy');

    Route::get('/projects/{project_id}/settings', [ProjectSettingController::class, 'show'])->name('settings.show');
    Route::patch('/projects/{project_id}/settings', [ProjectSettingController::class, 'update'])->name('settings.update');
    Route::delete('/projects/{project_id}/settings', [ProjectSettingController::class, 'destroy'])->name('settings.destroy');

    Route::get('/projects/{project_id}/maintenance', [MaintenanceController::class, 'show'])->name('maintenance.show');
    Route::put('/projects/{project_id}/maintenance', [MaintenanceController::class, 'update'])->name('maintenance.update');
    Route::delete('/projects/{project_id}/maintenance', [MaintenanceController::class, 'destroy'])->name('maintenance.destroy');

    Route::get('/logs', [LogController::class, 'index'])->name('logs.index');
    Route::get('/logs/labels', [LogController::class, 'labels'])->name('logs.labels');
    Route::get('/logs/labels/{label}/values', [LogController::class, 'labelValues'])->name('logs.label-values');

    Route::get('/projects/{project_id}/branches', [BranchController::class, 'index'])->name('branches.index');
    Route::get('/projects/{project_id}/dependencies', [DependencyController::class, 'index'])->name('dependencies.index');
    Route::get('/projects/{project_id}/dependencies/{id}', [DependencyController::class, 'show'])->name('dependencies.show');
});
