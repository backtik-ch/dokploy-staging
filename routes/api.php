<?php

use App\Http\Controllers\DeployController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::post('/{projectID}', [DeployController::class, 'deploy'])->middleware('auth:sanctum');
Route::delete('/{projectID}', [DeployController::class, 'delete'])->middleware('auth:sanctum');
