<?php

use App\Http\Controllers\CentralIntegrationController;
use App\Http\Controllers\CentralOAuthController;
use App\Http\Controllers\ElasticMemgraphService\OccupationController;
use App\Http\Controllers\TenantController;
use Illuminate\Support\Facades\Route;

foreach (config('tenancy.central_domains') as $domain) {
    Route::domain($domain)->group(function () {
        Route::get('/tenants/{tenant}/', [TenantController::class, 'show']);
        Route::get('/health', function () {
            return response()->json(['status' => 'ok']);
        });

        Route::group(['prefix' => '/ems'], function () {
            Route::post('/occupy/{tenant_id}', [OccupationController::class, 'occupy']);
        });

        // OAuth login central callbacks
        // it redirects to tenant application
        // this is needed to set up only one callback URL per provider
        // otherwise, each provider would need one callback per tenant
        Route::get('/auth/{provider}/callback', [CentralOAuthController::class, 'callback']);

        // integration central callbacks
        Route::get('/integrations/{provider}/oauth/callback', [CentralIntegrationController::class, 'callback']);
    });
}
