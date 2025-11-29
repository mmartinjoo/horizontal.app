<?php

namespace App\Services\Integration;

use Illuminate\Support\Collection;

class ProviderService
{
    /**
     * @return array<array<string, mixed>>
     */
    public function getActiveProviders(): array
    {
        $integrationConfig = config('features.integrations');
        return collect($integrationConfig)
            ->reject(fn (array $provider) => $provider['active'] === false)
            ->all();
    }
}