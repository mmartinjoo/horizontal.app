<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class FeatureController
{
    public function public(): JsonResponse
    {
        return response()->json([
            'features' => [
                'queue_based_auto_scaling_active' => config('features.registration.active'),
                'registration_open' => config('features.registration.active'),
            ],
        ]);
    }
}
