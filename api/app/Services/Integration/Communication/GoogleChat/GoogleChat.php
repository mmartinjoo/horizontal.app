<?php

namespace App\Services\Integration\Communication\GoogleChat;

use App\Models\GoogleChatIntegration;
use App\Services\Integration\Communication\Communication;
use App\Services\Integration\Communication\DataTransferObjects\Channel;
use App\Services\Integration\Communication\DataTransferObjects\Message;
use Exception;
use Google\Client;
use Google\Service\HangoutsChat;
use Google\Service\HangoutsChat\Space;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;

/**
 * Missing features:
 *  - Identifying threads vs stand-alone messages
 *  - Hanlding mentions in messages
 *  - Retrieving sender information
 * 
 * Google Chat is not that popular and the API is not that great.
 * These features aren't worth it.
 */
class GoogleChat implements Communication
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
     * @return LazyCollection<Channel>
     */
    public function channels(): LazyCollection
    {
        return LazyCollection::make(function () {
            $pageToken = null;
            while (true) {
                $data = $this->chat->spaces->listSpaces([
                    'pageSize' => 100,
                    'pageToken' => $pageToken,
                ]);

                $pageToken = $data->nextPageToken;                

                /** @var Space $space */
                foreach ($data->getSpaces() as $space) {
                    if ($space->spaceType !== 'SPACE') {
                        continue;
                    }
                    yield Channel::fromGoogleChat((array)$space);
                }

                if (!$pageToken) {
                    break;
                }

                // 50ms delay to avoid rate limits
                usleep(50_000);
            }
        });
    }

    /**
     * @return LazyCollection<Message>
     */
    public function messages(Channel $channel): LazyCollection
    {
        return LazyCollection::make(function () use ($channel) {
            $pageToken = null;
            while (true) {
                $data = $this->chat->spaces_messages->listSpacesMessages($channel->externalId, [
                    'pageSize' => 100,
                    'pageToken' => $pageToken,
                ]);

                $pageToken = $data->nextPageToken;
                foreach ($data->messages as $googleMessage) {
                    $message = Message::fromGoogleChat($channel, (array)$googleMessage);
                    $message->url = $this->messageLink($channel, $message);
                    yield $message;
                }

                if (!$pageToken) {
                    break;
                }

                // 50ms delay to avoid rate limits
                usleep(50_000);
            }
        });
    }

    /**
     * @return LazyCollection<Message>
     */
    public function threads(Channel $channel): LazyCollection
    {
        return LazyCollection::empty();
    }

    private function getValidIntegration(): GoogleChatIntegration
    {
        $integration = GoogleChatIntegration::first();
        if (!$integration) {
            throw new Exception('No Google integration found');
        }

        // TODO: Ensure token is valid (refresh if needed)
        return $integration;
    }

    private function messageLink(Channel $channel, Message $message): string
    {
        $channelID = Str::after($channel->externalId, "spaces/");
        $messageID = Str::after($message->externalId, "messages/");
        return "https://mail.google.com/chat/u/0/#chat/space/{$channelID}/message/{$messageID}";
    }
}