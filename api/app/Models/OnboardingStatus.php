<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingStatus extends Model
{
    protected $guarded = [];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'completed' => 'boolean',
            'completed_integrations' => 'array',
        ];
    }

    /**
     * Get the user that owns the onboarding status.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the user has completed onboarding by connecting at least one integration.
     */
    public function hasCompletedOnboarding(): bool
    {
        return $this->completed && ! empty($this->completed_integrations);
    }

    /**
     * Mark an integration as completed.
     */
    public function markIntegrationComplete(string $provider): void
    {
        $completedIntegrations = $this->completed_integrations ?? [];

        if (! in_array($provider, $completedIntegrations)) {
            $completedIntegrations[] = $provider;
            $this->update(['completed_integrations' => $completedIntegrations]);
        }
    }

    /**
     * Check if at least one integration is connected.
     */
    public function hasAnyIntegration(): bool
    {
        return ! empty($this->completed_integrations);
    }
}
