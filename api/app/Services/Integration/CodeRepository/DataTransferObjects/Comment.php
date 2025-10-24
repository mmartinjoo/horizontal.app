<?php

namespace App\Services\Integration\CodeRepository\DataTransferObjects;

use Illuminate\Support\Arr;

class Comment
{
    public function __construct(
        public string $externalId,
        public string $body,
        public string $author,
        public string $url,
    ) {
    }

    public static function fromGithub(array $data): self
    {
        return new static(
            externalId: $data['id'],
            body: $data['body'],
            author: Arr::get($data, 'user.login'),
            url: $data['html_url'],
        );
    }
}