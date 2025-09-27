<?php

use App\Http\Controllers\JiraIntegrationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

foreach (config('tenancy.central_domains') as $domain) {
    Route::domain($domain)->group(function () {
        Route::get('/multitenancy-test', function (Request $request) {
            dd(\App\Models\User::all());
        });

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
    });
}
