<?php

namespace App\Services\Integration\Communication\DataTransferObjects;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class Message
{
    /**
     * Only has values when the given message if the first message of a thread
     * @var Collection<Message>
     */
    public Collection $replies;

    /**
     * Only has value if the given message mentions other user
     * @var Collection<User> Users mentioned in a message
     */
    public Collection $mentions;

    public function __construct(
        public Channel $channel,
        public string $message,
        public string $externalUserId,
        public string $externalId,
        public Carbon $createdAt,
        public User $author,
    ) {
        $this->replies = collect();
        $this->mentions = collect();
    }

    public static function fromSlack(Channel $channel, array $data, User $author): self
    {
        return new self(
            channel: $channel,
            message: $data['text'],
            externalUserId: $data['user'],
            externalId: $data['ts'],
            createdAt: Carbon::parse($data['ts']),
            author: $author,
        );
    }

    public static function fromGoogleChat(Channel $channel, array $data): self
    {
        return new self(
            channel: $channel,
            message: $data['formattedText'],
            externalUserId: $data['sender']['name'],
            externalId: $data['name'],
            createdAt: Carbon::parse($data['createTime']),
            author: User::fromGoogleChat((array)$data['sender']),
        );
    }
}
