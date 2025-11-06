<?php

namespace App\Http\Controllers;

use App\Models\GoogleDriveFolder;
use App\Models\GoogleDriveIntegration;
use App\Services\Integration\Google\GoogleOAuthService;
use App\Services\Integration\Storage\DataTransferObjects\Folder;
use App\Services\Integration\Storage\GoogleDrive\GoogleDrive;
use App\Services\Url;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class GoogleDriveIntegrationController extends GoogleIntegrationController
{
    public function __construct(
        protected GoogleOAuthService $googleOAuthService,
    ) {
    }

    public function resources()
    {
        $googleDrive = app(GoogleDrive::class);
        $resources = $googleDrive->folders()
            ->map(function (Folder $folder) {
                return [
                    'id' => $folder->id,
                    'title' => $folder->path,
                    'description' => '',
                ];
            });

        return response()->json([
            'resources' => $resources,
        ]);
    }

    public function configure(Request $request)
    {
        $googleDrive = app(GoogleDrive::class);

        $request->validate([
            'selected_resources' => ['required', 'array'],
            'selected_resources.*' => ['required', 'string'],
        ]);

        $selectedResourceIds = $request->get('selected_resources');
        $integration = GoogleDriveIntegration::firstOrFail();

        GoogleDriveFolder::query()
            ->where('google_drive_integration_id', $integration->id)
            ->delete();

        $resources = $googleDrive->folders();

        /** @var Folder $resource */
        foreach ($resources as $resource) {
            if (in_array($resource->id, $selectedResourceIds)) {
                GoogleDriveFolder::create([
                    'name' => $resource->path,
                    'external_id' => $resource->id,
                    'google_drive_integration_id' => $integration->id,
                ]);
            }
        }
    }

    protected function hasExistingIntegration(): bool
    {
        return GoogleDriveIntegration::count() !== 0;
    }

    protected function getIntegration(): Model
    {
        return GoogleDriveIntegration::first();
    }

    protected function getStateCacheKey(): string
    {
        return 'google_drive_oauth_state-';
    }

    protected function createIntegration(array $userInfo, array $tokenData, Carbon $expiresAt): Model
    {
        return GoogleDriveIntegration::create([
            'user_name' => $userInfo['displayName'] ?? $userInfo['name'] ?? null,
            'user_email' => $userInfo['email'] ?? null,
            'google_user_id' => $userInfo['id'] ?? null,
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'] ?? null,
            'expires_at' => $expiresAt,
            'scope' => isset($tokenData['scope']) ? explode(',', $tokenData['scope']) : ['read', 'write'],
        ]);
    }

    protected function createRedirectUrlToOnboarding(string $errorMessage): string
    {
        return Url::createOnboardingCallbackFrontendUrl(
            tenant: tenancy()->tenant, 
            provider: 'google_drive',
            step: 'storage',
        );
    }
}