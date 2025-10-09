<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\JiraIntegration>
 */
class JiraIntegrationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'jira_base_url' => $this->faker->randomElement([
                'https://company.atlassian.net',
                'https://acme-corp.atlassian.net',
                'https://test-org.atlassian.net',
            ]),
            'cloud_id' => $this->faker->uuid(),
            'access_token' => $this->faker->sha256(),
            'refresh_token' => $this->faker->sha256(),
            'expires_at' => now()->addHours(1),
            'scope' => ['read:jira-user', 'read:jira-work'],
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subHours(1),
        ]);
    }

    public function withoutTokens(): static
    {
        return $this->state(fn (array $attributes) => [
            'access_token' => null,
            'refresh_token' => null,
            'expires_at' => null,
        ]);
    }
}
