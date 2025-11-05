<?php

namespace App\Http\Controllers;

use App\Models\GoogleIntegration;
use App\Services\Integration\Google\GoogleOAuthService;

class GoogleChatIntegrationController extends GoogleIntegrationController
{
    public function __construct(
        protected GoogleOAuthService $googleOAuthService,
    ) {
    }

    protected function hasExistingIntegration(): bool
    {
        return GoogleIntegration::count() !== 0;
    }
}