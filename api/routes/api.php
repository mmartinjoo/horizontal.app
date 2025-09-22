<?php

use App\Http\Controllers\GraphitiController;
use App\Http\Controllers\GraphitiDemoController;
use App\Http\Controllers\JiraIntegrationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/test', [\App\Http\Controllers\TestController::class, 'index']);
Route::get('/test/auth/token', [\App\Http\Controllers\TestController::class, 'token']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/questions/ask', [\App\Http\Controllers\QuestionController::class, 'ask']);
});

// Jira OAuth integration routes
Route::get('/integrations/jira/oauth/callback', [JiraIntegrationController::class, 'callback']);
Route::middleware('auth:sanctum')->prefix('/integrations/jira/oauth')->group(function () {
    Route::post('authorize', [JiraIntegrationController::class, 'authorize']);
    Route::get('status', [JiraIntegrationController::class, 'status']);
    Route::delete('disconnect', [JiraIntegrationController::class, 'disconnect']);
});

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
