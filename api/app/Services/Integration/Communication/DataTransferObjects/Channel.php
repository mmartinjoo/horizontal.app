<?php

namespace App\Services\Integration\Communication\DataTransferObjects;

class Channel
{
    public function __construct(
        public string $externalId,
        public string $name,
    ) {
    }

    public static function fromSlack(array $data): self
    {
        return  new self(
            externalId: $data['id'],
            name: $data['name'],
        );
    }

    public static function fromGoogleChat(array $data): self
    {
        return new self(
            externalId: $data['name'],
            name: $data['displayName'],
        );
    }
}
