<?php

declare(strict_types=1);

use App\Http\Controllers\GithubIntegrationController;
use App\Http\Controllers\GoogleChatIntegrationController;
use App\Http\Controllers\GoogleDriveIntegrationController;
use App\Http\Controllers\IndexingWorkflowController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\JiraIntegrationController;
use App\Http\Controllers\LinearIntegrationController;
use App\Http\Controllers\OAuthController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\SlackIntegrationController;
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
    Route::get('/test', [\App\Http\Controllers\TestController::class, 'index']);
    Route::get('/test/auth/token', [\App\Http\Controllers\TestController::class, 'token']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/questions/ask', [QuestionController::class, 'ask']);
        Route::get('/integrations', [IntegrationController::class, 'index']);
        Route::get('/indexing-workflow/status', [IndexingWorkflowController::class, 'status']);
        Route::get('/indexing-workflow/completed', [IndexingWorkflowController::class, 'completed']);
        Route::post('/workflows/start', [WorkflowController::class, 'start']);
        Route::post('/invitations', [InvitationController::class, 'store']);
    });

    Route::get('/invitations/{token}', [InvitationController::class, 'show']);

    Route::group(['prefix' => 'orchestrator'], function () {
        Route::post('/workflows/buckets', [WorkflowController::class, 'createBucket']);
        Route::post('/workflows/buckets/items', [WorkflowController::class, 'addItems']);
        Route::post('/workflows/buckets/items/processing', [WorkflowController::class, 'markItemsAsProcessing']);
        Route::post('/workflows/buckets/items/completed', [WorkflowController::class, 'markItemsAsCompleted']);
        Route::post('/workflows/buckets/items/failed', [WorkflowController::class, 'markItemsAsFailed']);
        Route::post('/documents/next-batch', [WorkflowController::class, 'nextBatch']);
    });

    Route::get('/integrations/jira/oauth/callback', [JiraIntegrationController::class, 'callback']);
    Route::get('/integrations/jira/resources', [JiraIntegrationController::class, 'resources']);
    Route::post('/integrations/jira/configure', [JiraIntegrationController::class, 'configure']);
    Route::middleware('auth:sanctum')->prefix('/integrations/jira/oauth')->group(function () {
        Route::post('authorize', [JiraIntegrationController::class, 'authorize']);
        Route::get('status', [JiraIntegrationController::class, 'status']);
        Route::delete('disconnect', [JiraIntegrationController::class, 'disconnect']);
    });

    Route::get('/integrations/linear/oauth/callback', [LinearIntegrationController::class, 'callback']);
    Route::get('/integrations/linear/resources', [LinearIntegrationController::class, 'resources']);
    Route::post('/integrations/linear/configure', [LinearIntegrationController::class, 'configure']);
    Route::middleware('auth:sanctum')->prefix('/integrations/linear/oauth')->group(function () {
        Route::post('authorize', [LinearIntegrationController::class, 'authorize']);
        Route::get('status', [LinearIntegrationController::class, 'status']);
        Route::delete('disconnect', [LinearIntegrationController::class, 'disconnect']);
    });

    Route::get('/integrations/google_chat/oauth/callback', [GoogleChatIntegrationController::class, 'callback']);
    Route::get('/integrations/google_chat/resources', [GoogleChatIntegrationController::class, 'resources']);
    Route::post('/integrations/google_chat/configure', [GoogleChatIntegrationController::class, 'configure']);
    Route::middleware('auth:sanctum')->prefix('/integrations/google_chat/oauth')->group(function () {
        Route::post('authorize', [GoogleChatIntegrationController::class, 'authorize']);
        Route::get('status', [GoogleChatIntegrationController::class, 'status']);
        Route::delete('disconnect', [GoogleChatIntegrationController::class, 'disconnect']);
    });

    Route::get('/integrations/google_drive/oauth/callback', [GoogleDriveIntegrationController::class, 'callback']);
    Route::get('/integrations/google_drive/resources', [GoogleDriveIntegrationController::class, 'resources']);
    Route::post('/integrations/google_drive/configure', [GoogleDriveIntegrationController::class, 'configure']);
    Route::middleware('auth:sanctum')->prefix('/integrations/google_drive/oauth')->group(function () {
        Route::post('authorize', [GoogleDriveIntegrationController::class, 'authorize']);
        Route::get('status', [GoogleDriveIntegrationController::class, 'status']);
        Route::delete('disconnect', [GoogleDriveIntegrationController::class, 'disconnect']);
    });

    Route::get('/integrations/github/oauth/callback', [GithubIntegrationController::class, 'callback']);
    Route::get('/integrations/github/resources', [GithubIntegrationController::class, 'resources']);
    Route::post('/integrations/github/configure', [GithubIntegrationController::class, 'configure']);
    Route::middleware('auth:sanctum')->prefix('/integrations/github/oauth')->group(function () {
        Route::post('authorize', [GithubIntegrationController::class, 'authorize']);
        Route::get('status', [GithubIntegrationController::class, 'status']);
        Route::delete('disconnect', [GithubIntegrationController::class, 'disconnect']);
    });

    Route::get('/integrations/slack/oauth/callback', [SlackIntegrationController::class, 'callback']);
    Route::get('/integrations/slack/resources', [SlackIntegrationController::class, 'resources']);
    Route::post('/integrations/slack/configure', [SlackIntegrationController::class, 'configure']);
    Route::middleware('auth:sanctum')->prefix('/integrations/slack/oauth')->group(function () {
        Route::post('authorize', [SlackIntegrationController::class, 'authorize']);
        Route::get('status', [SlackIntegrationController::class, 'status']);
        Route::delete('disconnect', [SlackIntegrationController::class, 'disconnect']);
    });

    // OAuth routes
    Route::prefix('auth')->group(function () {
        Route::get('/{provider}/redirect', [OAuthController::class, 'redirectToProvider']);
        Route::get('/{provider}/callback', [OAuthController::class, 'handleProviderCallback']);
    });
});
