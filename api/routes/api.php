<?php

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
    });
}
