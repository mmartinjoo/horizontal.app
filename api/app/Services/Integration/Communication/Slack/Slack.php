<?php

namespace App\Services\Integration\Communication\Slack;

use App\Services\Integration\Communication\Communication;
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
use Illuminate\Support\LazyCollection;
use Throwable;

class Slack implements Communication
{
    /** @var array<User> */
    private static array $userCache;

    public function __construct(
        private string $baseUrl,
        private string $botUserOauthToken,
    ) {
    }

    /**
     * @return LazyCollection<Channel>
     * @throws FailedToLoadChannelsException
     * @throws ConnectionException
     * @throws RequestException
     */
    public function channels(): LazyCollection
    {
        $cursor = null;
        return LazyCollection::make(function () use ($cursor) {
            while (true) {
                $data = [
                    'limit' => 100,
                ];
                if ($cursor) {
                    $data['cursor'] = $cursor;
                }
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->botUserOauthToken,
                ])
                    ->get($this->baseUrl . '/conversations.list', $data)
                    ->throw()
                    ->json();

                if (!$response['ok']) {
                    throw new FailedToLoadChannelsException('Failed to load channels. Response: ' . json_encode($response));
                }

                foreach ($response['channels'] as $channel) {
                    yield Channel::fromSlack($channel);
                }

                $nextCursor = Arr::get($response, 'response_metadata.next_cursor');
                if (!$nextCursor) {
                    break;
                }
                $cursor = $nextCursor;
            }
        });
    }

    /**
     * @param Channel $channel
     * @return LazyCollection<Message>
     * @throws ConnectionException
     * @throws FailedToLoadMessagesException
     * @throws RequestException
     */
    public function messages(Channel $channel): LazyCollection
    {
        return LazyCollection::make(function () use ($channel) {
            $cursor = null;
            while (true) {
                $data = [
                    'channel' => $channel->externalId,
                    'oldest' => now()->subMonths(3)->timestamp,
                    'limit' => 100,
                    "inclusive" => true,
                ];            
                if ($cursor) {
                    $data['cursor'] = $cursor;
                }

                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->botUserOauthToken,
                ])
                    ->get($this->baseUrl . '/conversations.history', $data)
                    ->throw()
                    ->json();

                if (!$response['ok']) {
                    throw new FailedToLoadMessagesException('Failed to load messages. Response: ' . json_encode($response));
                }

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

                    yield $this->makeMessageWithMentions($channel, $message);
                }

                $nextCursor = Arr::get($response, 'response_metadata.next_cursor');
                if (!$nextCursor) {
                    break;
                }
                $cursor = $nextCursor;    
                
                // 50ms delay to avoid rate limits
                usleep(50_000);
            }
        });
    }

    /**
     * @param Channel $channel
     * @return LazyCollection<Message>
     * @throws ConnectionException
     * @throws FailedToLoadMessagesException
     * @throws RequestException
     */
    public function threads(Channel $channel): LazyCollection
    {
        return LazyCollection::make(function () use ($channel) {
            $cursor = null;
            while (true) {
                $data = [
                    'channel' => $channel->externalId,
                    'oldest' => now()->subMonths(3)->timestamp,
                    'limit' => 100,
                    'inclusive' => true,
                ];
                if ($cursor) {
                    $data['cursor'] = $cursor;
                }

                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->botUserOauthToken,
                ])
                    ->get($this->baseUrl . '/conversations.history', $data)
                    ->throw()
                    ->json();

                if (!$response['ok']) {
                    throw new FailedToLoadMessagesException('Failed to load messages. Response: ' . json_encode($response));
                }

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
                    
                    $thread = $this->makeMessageWithMentions($channel, $message);
                    $thread->replies = $this->replies($channel, $thread);
                    yield $thread;
                }

                $nextCursor = Arr::get($response, 'response_metadata.next_cursor');
                if (!$nextCursor) {
                    break;
                }
                $cursor = $nextCursor;    
                
                // 50ms delay to avoid rate limits
                usleep(50_000);
            }
        });
    }

    /**
     * No need for LazyCollection since it returns only the replies for a given message (a few dozens)
     * 
     * @return Collection<Message> 
     */
    private function replies(Channel $channel, Message $thread): Collection
    {
        $replies = [];
        $cursor = null;
        while (true) {
            $data = [
                'channel' => $channel->externalId,
                'ts' => $thread->externalId,
                'oldest' => now()->subMonths(3)->timestamp,
                'limit' => 100,
                'inclusive' => true,
            ];
            if ($cursor) {
                $data['cursor'] = $cursor;
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->botUserOauthToken,
            ])
                ->get($this->baseUrl . '/conversations.replies', $data)
                ->throw()
                ->json();

            if (!$response['ok']) {
                throw new FailedToLoadMessagesException('Failed to load replies. Response: ' . json_encode($response));
            }

            foreach ($response['messages'] as $message) {
                if ($message['ts'] === $thread->externalId) {
                    continue;
                }
                $replies[] = $this->makeMessageWithMentions($channel, $message);
            }

            $nextCursor = Arr::get($response, 'response_metadata.next_cursor');
            if (!$nextCursor) {
                break;
            }
            $cursor = $nextCursor;
            
            // 50ms delay to avoid rate limits
            usleep(50_000);
        }
        return collect($replies);
    }

    public function permalink(string $channelID, string $messageID): string
    {
        $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->botUserOauthToken,
            ])
            ->get($this->baseUrl . '/chat.getPermalink', [
                'channel' => $channelID,
                'message_ts' => $messageID,
            ])
            ->throw()
            ->json();

        if (!$response['ok']) {
            throw new FailedToLoadMessagesException('Failed to fetch permalink. Response: ' . json_encode($response));
        }
        return $response['permalink'];
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
                'limit' => 500,
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
        try {
            $author = $this->userByID($data['user']);
        } catch (UserNotFoundException) {
            $author = new User(
                externalId: 'unknown',
                username: 'unknown',
                realName: 'Unknown',
            );
        }
        
        $message = Message::fromSlack($channel, $data, $author);
        return $this->swapMentions($message);
    }

    /**
     * In the Slack message, mentions are represented like this:
     *  "hey <@U3RB7BE81AW> what's up?"
     * 
     * This function swaps these IDs with the real names:
     *  "hey John Doe what's up?"
     * 
     * It also sets the `mentions` property.
     */
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
            $message->mentions = collect($users);
            return $message;
        } catch (Throwable) {
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
