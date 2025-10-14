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

    public function __construct(
        public Channel $channel,
        public string $message,
        public string $externalUserId,
        public string $externalId,
        public Carbon $createdAt,
    ) {
    }

    public static function fromSlack(Channel $channel, array $data): self
    {
        return new self(
            channel: $channel,
            message: $data['text'],
            externalUserId: $data['user'],
            externalId: $data['ts'],
            createdAt: Carbon::parse($data['ts']),
        );
    }
}
