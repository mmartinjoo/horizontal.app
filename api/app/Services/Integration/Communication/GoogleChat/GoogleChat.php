<?php

namespace App\Services\Integration\Communication\GoogleChat;

use App\Models\GoogleIntegration;
use App\Services\Integration\Communication\DataTransferObjects\Channel;
use App\Services\Integration\Communication\DataTransferObjects\Message;
use Exception;
use Google\Client;
use Google\Service\HangoutsChat;
use Google\Service\HangoutsChat\Space;
use Illuminate\Support\Collection;

class GoogleChat
{
    private HangoutsChat $chat;

    public function __construct()
    {
        $integration = $this->getValidIntegration();
        $client = new Client();
        $client->setAccessToken($integration->access_token);    
        $this->chat = new HangoutsChat($client);
    }

    /**
     * @return Collection<Channel>
     */
    public function channels(): Collection
    {
        $spaces = $this->chat->spaces->listSpaces([
            'pageSize' => 1000,
        ]);

        $channels = collect();

        /** @var Space $space */
        foreach ($spaces as $space) {
            if ($space->spaceType !== 'SPACE') {
                continue;
            }
            $channels[] = Channel::fromGoogleChat((array)$space);
        }
        return $channels;
    }

    /**
     * @return Collection<Message>
     */
    public function messages(Channel $channel): Collection
    {
        $data = $this->chat->spaces_messages->listSpacesMessages($channel->externalId);
        $messages = collect();
        foreach ($data->messages as $googleMessage) {
            $messages[] = Message::fromGoogleChat($channel, (array)$googleMessage);
        }
        return $messages;
    }

    private function getValidIntegration(): GoogleIntegration
    {
        $integration = GoogleIntegration::first();
        if (!$integration) {
            throw new Exception('No Google integration found');
        }

        // TODO: Ensure token is valid (refresh if needed)
        return $integration;
    }
}