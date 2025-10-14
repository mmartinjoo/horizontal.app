<?php

namespace App\Services\Integration\Communication\Slack;

use App\Services\Integration\Communication\DataTransferObjects\Channel;
use App\Services\Integration\Communication\DataTransferObjects\Message;
use App\Services\Integration\Communication\DataTransferObjects\User;
use App\Services\Integration\Communication\Exceptions\FailedToLoadChannelsException;
use App\Services\Integration\Communication\Exceptions\FailedToLoadMessagesException;
use App\Services\Integration\Communication\Exceptions\UserNotFoundException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Throwable;

class Slack
{
    /** @var array<User> */
    private static array $userCache;

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

    /**
     * @param Channel $channel
     * @return Collection<Message>
     * @throws ConnectionException
     * @throws FailedToLoadMessagesException
     * @throws RequestException
     */
    public function messages(Channel $channel): Collection
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->botUserOauthToken,
        ])
            ->get($this->baseUrl . '/conversations.history', [
                'channel' => $channel->externalId,
                'oldest' => now()->subMonths(3)->timestamp,
                'limit' => 100,
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
            if (Arr::get($message, 'thread_ts') === $message['ts']) {
                // this is a thread. it's processed in a dedicated function
                continue;
            }            
            $messages[] = $this->makeMessageWithMentions($channel, $message);
        }
        return collect($messages);
    }

    /**
     * @param Channel $channel
     * @return Collection<Message>
     * @throws ConnectionException
     * @throws FailedToLoadMessagesException
     * @throws RequestException
     */
    public function threads(Channel $channel): Collection
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->botUserOauthToken,
        ])
            ->get($this->baseUrl . '/conversations.history', [
                'channel' => $channel->externalId,
                'oldest' => now()->subMonths(3)->timestamp,
                'limit' => 100,
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
            if (Arr::get($message, 'thread_ts') !== $message['ts']) {
                // this is an individual message without replies. it's processed in a dedicated function
                continue;
            }
            $messages[] = $this->makeMessageWithMentions($channel, $message);
        }
        foreach ($messages as $message) {
            $message->replies = $this->replies($channel, $message);
        }
        return collect($messages);
    }

    public function replies(Channel $channel, Message $thread): Collection
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->botUserOauthToken,
        ])
            ->get($this->baseUrl . '/conversations.replies', [
                'channel' => $channel->externalId,
                'ts' => $thread->externalId,
                'oldest' => now()->subMonths(3)->timestamp,
                'limit' => 100,
                "inclusive" => true,
            ])
            ->throw()
            ->json();

        if (!$response['ok']) {
            throw new FailedToLoadMessagesException('Failed to load replies. Response: ' . json_encode($response));
        }

        $replies = collect();
        foreach ($response['messages'] as $message) {
            if ($message['ts'] === $thread->externalId) {
                continue;
            }
            $replies[] = $this->makeMessageWithMentions($channel, $message);
        }
        return collect($replies);
    }

    /**
     * @return Collection<User>
     */
    public function users(): Collection
    {
        if (!empty(self::$userCache)) {
            return collect(self::$userCache);
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->botUserOauthToken,
        ])
            ->get($this->baseUrl . '/users.list', [
                'limit' => 100,
            ])
            ->throw()
            ->json();

        if (!$response['ok']) {
            throw new FailedToLoadMessagesException('Failed to load replies. Response: ' . json_encode($response));
        }

        $users = collect();
        foreach ($response['members'] as $member) {
            if ($member['is_bot'] || $member['id'] === 'USLACKBOT') {
                continue;
            }
            $users[] = User::fromSlack($member);
        }
        self::$userCache = $users->toArray();
        return $users;
    }

    public function userByID(string $id): User
    {
        $user = $this->users()
            ->filter(fn (User $user) => $user->externalId === $id)
            ->first();

        if (!$user) {
            throw new UserNotFoundException("User not found with ID: $id");
        }

        return $user;
    }

    private function makeMessageWithMentions(Channel $channel, array $data): Message
    {
        $message = Message::fromSlack($channel, $data);
        return $this->swapMentions($message);
    }

    private function swapMentions(Message $message): Message
    {
        try {
            $userIDs = $this->getMentionIDs($message);
            $users = [];
            foreach ($userIDs as $id) {
                $users[] = $this->userByID($id);
            }

            foreach ($userIDs as $i => $id) {
                if (isset($users[$i])) {
                    $message->message = str_replace("<@$id>", $users[$i]->realName, $message->message);
                }
            }
            return $message;
        } catch (Throwable $ex) {
            return $message;
        }
    }

    /**
     * @return Collection<string> The mentioned user IDs in a given message
     */
    private function getMentionIDs(Message $message): Collection
    {
        $pattern = '/<@([A-Z0-9]{11})>/';
        preg_match_all($pattern, $message->message, $matches);

        // Contains strings like this: <@U09ED7YES5B>
        $mentions = $matches[0];
        $ids = [];
        foreach ($mentions as $mention) {
            // Transforming into raw ID: U09ED7YES5B
            $id = Str::replaceFirst('<', '', $mention);
            $id = Str::replaceFirst('@', '', $id);
            $id = Str::replaceLast('>', '', $id);
            $ids[] = $id;
        }
        return collect($ids);
    }
}
