<?php

namespace App\Services\Integration\CodeRepository\Github;

use Carbon\Carbon;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;

class GithubOAuth
{
    public function __construct(
        private array $config,
    ) {}

    public function generateAuthorizationUrl(): array
    {
        $appName = $this->config['app_name'];
        return [
            'authorization_url' => "https://github.com/apps/{$appName}/installations/new",
        ];
    }

    public function getInstallationToken(int $installationId): string
    {
        $baseUrl = $this->config['base_url'];
        return Http::withToken($this->generateJWT())
            ->acceptJson()
            ->withHeaders([
                'X-GitHub-Api-Version' => '2022-11-28',
            ])
            ->throw()
            ->post("{$baseUrl}/app/installations/{$installationId}/access_tokens")
            ->json('token');
    }

    public function getInstallations(): array
    {
        $baseUrl = $this->config['base_url'];
        return Http::withToken($this->generateJWT())
            ->acceptJson()        
            ->withHeaders([
                'X-GitHub-Api-Version' => '2022-11-28',
            ])
            ->throw()
            ->get("{$baseUrl}/app/installations")
            ->json();
    }

    private function generateJWT(): string
    {
        $now = Carbon::now();
        $payload = [
            'iat' => $now->timestamp,
            'exp' => $now->addMinutes(10)->timestamp,
            'iss' => $this->config['app_id'],
        ];
        return JWT::encode($payload, $this->config['private_key'], 'RS256');
    }
}