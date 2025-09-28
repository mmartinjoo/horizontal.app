<?php

use App\Http\Controllers\JiraIntegrationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

foreach (config('tenancy.central_domains') as $domain) {
    Route::domain($domain)->group(function () {
        Route::get('/multitenancy-test', function (Request $request) {
            dd(\App\Models\User::all());
        });
    });
}
