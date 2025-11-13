<?php

namespace App\Services\Integration\TaskManagement\DataTransferObjects;

use Carbon\Carbon;
use Illuminate\Support\Arr;

class Issue
{
    public function __construct(
        public string $id,
        public string $title,
        public string $description,
        public string $assignee,
        public string $status,
        public string $url,
        public Carbon $createdAt,
        public Carbon $updatedAt,
    ) {}

    public function getLastUpdatedAt(): Carbon
    {
        // If there were no updates, updatedAt is 1900-01-01
        return $this->updatedAt->max($this->createdAt);
    }

    public static function fromJira(array $data, string $description): self
    {
        return new static(
            id: $data['key'],
            title: Arr::get($data, 'fields.summary', ''),
            description: $description,
            assignee: Arr::get($data, 'fields.assignee.displayName', ''),
            status: strtolower(Arr::get($data, 'fields.status.name', '')),
            url: Arr::get($data, 'self'),
            createdAt: Carbon::parse(Arr::get($data, 'fields.created', '1900-01-01T00:00:00.000+0000')),
            updatedAt: Carbon::parse(Arr::get($data, 'fields.updated', '1900-01-01T00:00:00.000+0000')),
        );
    }

    public static function fromLinear(array $data, string $description): self
    {
        return new static(
            id: Arr::get($data, 'identifier', ''),
            title: Arr::get($data, 'title', ''),
            description: $description,
            assignee: Arr::get($data, 'assignee', ''),
            status: strtolower(Arr::get($data, 'state', '')),
            url: Arr::get($data, 'url', ''),
            createdAt: Carbon::parse(Arr::get($data, 'createdAt', '1900-01-01T00:00:00.000Z')),
            updatedAt: Carbon::parse(Arr::get($data, 'updatedAt', '1900-01-01T00:00:00.000Z')),
        );
    }

    public function toString(): string
    {
        $str = '';
        foreach ($this as $key => $value) {
            $str .= $key.':'.$value.' ';
        }

        return $str;
    }
}
