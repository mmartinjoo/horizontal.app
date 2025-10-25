<?php

namespace App\Services\Integration\CodeRepository\DataTransferObjects;

use Carbon\Carbon;
use Illuminate\Support\Arr;

class Issue
{
    public function __construct(
        public string $externalId,
        public string $title,
        public string $body,
        public string $author,
        public string $url,
        public string $state,
        public Carbon $createdAt,
    ) {
    }

    public static function fromGithub(array $data): self
    {
        return new static(
            externalId: $data['id'],
            title: $data['title'],
            body: $data['body'],
            author: Arr::get($data, 'user.login'),
            url: $data['html_url'],
            state: $data['state'],
            createdAt: Carbon::parse($data['created_at']),
        );
    }
}