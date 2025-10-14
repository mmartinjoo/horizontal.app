<?php

namespace App\Services\Integration\Communication\DataTransferObjects;

use Illuminate\Support\Arr;

class Message
{
    public function __construct(
        public Channel $channel,
        public string $message,
        public string $externalUserId,
        public string $externalId,
        public ?string $externalThreadId = null,
    ) {
    }

    public static function fromSlack(Channel $channel, array $data): self
    {
        return new self(
            channel: $channel,
            message: $data['text'],
            externalUserId: $data['user'],
            externalId: $data['ts'],
            externalThreadId: Arr::get($data, 'thread_ts'),
        );
    }
}
