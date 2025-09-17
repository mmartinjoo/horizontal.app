<?php

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
