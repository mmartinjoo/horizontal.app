<?php

namespace App\Services\Integration\Communication\DataTransferObjects;

class Message
{
    public function __construct(
        public Channel $channel,
        public string $message,
        public string $externalUserId,
        public string $externalId,
    ) {
    }

    public static function fromSlack(Channel $channel, array $data): self
    {
        return new self(
            channel: $channel,
            message: $data['text'],
            externalUserId: $data['user'],
            externalId: $data['client_msg_id'],
        );
    }
}
