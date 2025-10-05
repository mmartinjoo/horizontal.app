<?php

use App\Http\Controllers\TenantController;
use Illuminate\Support\Facades\Route;

foreach (config('tenancy.central_domains') as $domain) {
    Route::domain($domain)->group(function () {
        Route::get('/tenants/{tenant}/', [TenantController::class, 'show']);
        Route::get('/tenants', function () {
            return \App\Models\Tenant::all();
        });
    });
}
