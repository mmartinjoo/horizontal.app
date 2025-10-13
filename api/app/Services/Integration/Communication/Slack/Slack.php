<?php

namespace App\Services\Integration\Communication\Slack;

use App\Services\Integration\Communication\DataTransferObjects\Channel;
use App\Services\Integration\Communication\DataTransferObjects\Message;
use App\Services\Integration\Communication\Exceptions\FailedToLoadChannelsException;
use App\Services\Integration\Communication\Exceptions\FailedToLoadMessagesException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class Slack
{
    public function __construct(
        private string $baseUrl,
        private string $botUserOauthToken
    ) {
    }

    /**
     * @return Collection<Channel>
     * @throws FailedToLoadChannelsException
     * @throws ConnectionException
     * @throws RequestException
     */
    public function channels(): Collection
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->botUserOauthToken,
        ])
            ->get($this->baseUrl . '/conversations.list')
            ->throw()
            ->json();

        if (!$response['ok']) {
            throw new FailedToLoadChannelsException('Failed to load channels. Response: ' . json_encode($response));
        }

        $channels = [];
        foreach ($response['channels'] as $channel) {
            $channels[] = Channel::fromSlack($channel);
        }
        return collect($channels);
    }

    public function conversations(Channel $channel)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->botUserOauthToken,
        ])
            ->get($this->baseUrl . '/conversations.history', [
                'channel' => $channel->externalId,
                'oldest' => now()->subMonths(3)->timestamp,
                'limit' => 20,
                "inclusive" => true,
            ])
            ->throw()
            ->json();

        if (!$response['ok']) {
            throw new FailedToLoadMessagesException('Failed to load messages. Response: ' . json_encode($response));
        }

        $messages = [];
        foreach ($response['messages'] as $message) {
            if ($message['type'] !== 'message') {
                continue;
            }
            if (Arr::get($message, 'subtype') !== null) {
                // channel_join, etc
                continue;
            }
            $messages[] = Message::fromSlack($channel, $message);
        }

        dd($messages);
    }
}
