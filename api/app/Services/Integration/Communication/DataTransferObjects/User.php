<?php

namespace App\Services\Integration\Communication\DataTransferObjects;

use Illuminate\Support\Arr;

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

    public static function fromGoogleChat(array $data): self
    {
        return new self(
            externalId: $data['name'],
            username: Arr::get($data, 'displayName', 'unknown') ?? 'unknown',
            realName: Arr::get($data, 'displayName', 'unknown') ?? 'unknown',
        );
    }
}
