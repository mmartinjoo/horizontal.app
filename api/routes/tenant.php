<?php

declare(strict_types=1);

use App\Http\Controllers\GithubIntegrationController;
use App\Http\Controllers\JiraIntegrationController;
use App\Http\Controllers\LinearIntegrationController;
use App\Http\Controllers\GoogleIntegrationController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\WorkflowController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant routes for your application.
| These routes are loaded by the TenantRouteServiceProvider.
|
| Feel free to customize them however you want. Good luck!
|
*/

Route::middleware([
    'api',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->prefix('/api')->group(function () {
    Route::get('/multitenancy-test', function () {
        dd(\App\Models\User::first());
    });

    Route::get('/test', [\App\Http\Controllers\TestController::class, 'index']);
    Route::get('/test/auth/token', [\App\Http\Controllers\TestController::class, 'token']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/questions/ask', [QuestionController::class, 'ask']);
    });

    Route::group(['prefix' => 'orchestrator'], function () {
        Route::post('/workflows/buckets', [WorkflowController::class, 'createBucket']);
        Route::post('/workflows/buckets/items', [WorkflowController::class, 'addItems']);
    });    

    Route::get('/integrations/jira/oauth/callback', [JiraIntegrationController::class, 'callback']);
    Route::middleware('auth:sanctum')->prefix('/integrations/jira/oauth')->group(function () {
        Route::post('authorize', [JiraIntegrationController::class, 'authorize']);
        Route::get('status', [JiraIntegrationController::class, 'status']);
        Route::delete('disconnect', [JiraIntegrationController::class, 'disconnect']);
    });

    Route::get('/integrations/linear/oauth/callback', [LinearIntegrationController::class, 'callback']);
    Route::middleware('auth:sanctum')->prefix('/integrations/linear/oauth')->group(function () {
        Route::post('authorize', [LinearIntegrationController::class, 'authorize']);
        Route::get('status', [LinearIntegrationController::class, 'status']);
        Route::delete('disconnect', [LinearIntegrationController::class, 'disconnect']);
    });

    Route::get('/integrations/google/oauth/callback', [GoogleIntegrationController::class, 'callback']);
    Route::middleware('auth:sanctum')->prefix('/integrations/google/oauth')->group(function () {
        Route::post('authorize', [GoogleIntegrationController::class, 'authorize']);
        Route::get('status', [GoogleIntegrationController::class, 'status']);
        Route::delete('disconnect', [GoogleIntegrationController::class, 'disconnect']);
    });

    Route::get('/integrations/github/oauth/callback', [GithubIntegrationController::class, 'callback']);
    Route::middleware('auth:sanctum')->prefix('/integrations/github/oauth')->group(function () {
        Route::post('authorize', [GithubIntegrationController::class, 'authorize']);
        Route::get('status', [GithubIntegrationController::class, 'status']);
        Route::delete('disconnect', [GithubIntegrationController::class, 'disconnect']);
    });
});
