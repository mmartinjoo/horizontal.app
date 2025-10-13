<?php

use App\Http\Controllers\GraphitiController;
use App\Http\Controllers\GraphitiDemoController;
use App\Http\Controllers\TenantController;
use Illuminate\Support\Facades\Route;

foreach (config('tenancy.central_domains') as $domain) {
    Route::domain($domain)->group(function () {
        Route::get('/tenants/{tenant}/', [TenantController::class, 'show']);
        Route::get('/tenants', function () {
            return \App\Models\Tenant::all();
        });
        Route::get('/health', function() {
            return response()->json(['status' => 'ok']);
        });
    });
}

// Graphiti memory integration routes
Route::prefix('/graphiti')->group(function () {
    Route::get('/health', [GraphitiController::class, 'healthCheck']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/memory', [GraphitiController::class, 'addMemory']);
        Route::post('/memory/search', [GraphitiController::class, 'searchMemory']);
        Route::post('/search/enhanced', [GraphitiController::class, 'searchWithMemory']);
        Route::post('/track/click', [GraphitiController::class, 'trackClick']);
        Route::get('/patterns/user/{userId}', [GraphitiController::class, 'getUserPatterns']);
    });

    // Demo endpoints (separate controller for better organization)
    Route::prefix('/demo')->middleware('auth:sanctum')->group(function () {
        Route::get('/info', [GraphitiDemoController::class, 'getDemoInfo']);
        Route::post('/run', [GraphitiDemoController::class, 'runDemo']);
        Route::get('/memories', [GraphitiDemoController::class, 'viewMemories']);
        Route::delete('/clear', [GraphitiDemoController::class, 'clearMemories']);
    });
});
