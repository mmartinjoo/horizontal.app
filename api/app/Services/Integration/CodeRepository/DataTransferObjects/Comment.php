<?php

namespace App\Services\Integration\CodeRepository\DataTransferObjects;

use Carbon\Carbon;
use Illuminate\Support\Arr;

class Comment
{
    public function __construct(
        public string $externalId,
        public string $body,
        public string $author,
        public string $url,
        public Carbon $createdAt,
    ) {
    }

    public static function fromGithub(array $data): self
    {
        return new static(
            externalId: $data['id'],
            body: $data['body'],
            author: Arr::get($data, 'user.login'),
            url: $data['html_url'],
            createdAt: Carbon::parse($data['created_at']),
        );
    }
}