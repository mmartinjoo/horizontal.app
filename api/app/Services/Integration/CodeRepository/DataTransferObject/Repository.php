<?php

namespace App\Services\Integration\CodeRepository\DataTransferObject;

class Repository
{
    public function __construct(
        public string $externalId,
        public string $name,        
        public string $owner,
    ) {
    }

    public static function fromGitHub(array $data): self
    {
        return new static(
            externalId: $data['id'],
            name: $data['name'],
            owner: $data['owner']['login'],
        );
    }
}