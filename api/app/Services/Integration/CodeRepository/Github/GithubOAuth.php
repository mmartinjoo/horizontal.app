<?php

namespace App\Services\Integration\CodeRepository\Github;

use Carbon\Carbon;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;

class GithubOAuth
{
    public function __construct(
        private string $appId,
        private string $privateKeyPath,
        private string $baseUrl = 'https://api.github.com'
    ) {}

    public function getInstallationToken(int $installationId): string
    {
        return Http::withToken($this->generateJWT())
            ->acceptJson()
            ->withHeaders([
                'X-GitHub-Api-Version' => '2022-11-28',
            ])
            ->throw()
            ->post("{$this->baseUrl}/app/installations/{$installationId}/access_tokens")
            ->json('token');
    }

    public function getInstallations(): array
    {
        return Http::withToken($this->generateJWT())
            ->acceptJson()        
            ->withHeaders([
                'X-GitHub-Api-Version' => '2022-11-28',
            ])
            ->throw()
            ->get("{$this->baseUrl}/app/installations")
            ->json();
    }

    private function generateJWT(): string
    {
        $now = Carbon::now();
        $payload = [
            'iat' => $now->timestamp,
            'exp' => $now->addMinutes(30)->timestamp,
            'iss' => $this->appId,
        ];

        $privateKey = file_get_contents($this->privateKeyPath);
        return JWT::encode($payload, $privateKey, 'RS256');
    }
}