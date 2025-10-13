<?php

namespace App\Services\Integration\Communication\Slack;

use Illuminate\Support\Facades\Http;

class Slack
{
    public function __construct(
        private string $baseUrl,
        private string $botUserOauthToken
    ) {
    }

    public function conversations()
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->botUserOauthToken,
        ])
            ->get($this->baseUrl . '/conversations.list')
            ->throw()
            ->json();

        return $response;
    }
}
