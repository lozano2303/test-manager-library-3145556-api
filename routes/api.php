<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Library\Adapters\Inbound\Http\HealthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Health check endpoint - root level
Route::get('/health', [HealthController::class, 'check']);

// Version 1 API routes
Route::prefix('v1')->group(function () {
    // Health check with version prefix
    Route::get('/health', [HealthController::class, 'check']);
    
    // Authentication routes
    Route::post('/auth/login', function (Request $request) {
        // TODO: Implement authentication
        return response()->json(['message' => 'Authentication endpoint - not implemented yet']);
    });
    
    // Protected routes placeholder
    Route::middleware(['auth:sanctum'])->group(function () {
        // TODO: Add protected routes here
    });
});