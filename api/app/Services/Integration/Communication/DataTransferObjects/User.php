<?php

namespace App\Services\Integration\Communication\DataTransferObjects;

class User
{
    public function __construct(
        public string $externalId,
        public string $username,
        public string $realName,
    ) {
    }

    public static function fromSlack(array $data): self
    {
        return new self(
            externalId: $data['id'],
            username: $data['name'],
            realName: $data['real_name'],
        );
    }
}
