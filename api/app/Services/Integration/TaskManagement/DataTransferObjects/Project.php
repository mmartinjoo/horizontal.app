<?php

namespace App\Services\Integration\TaskManagement\DataTransferObjects;

use Carbon\Carbon;
use Illuminate\Support\Arr;

class Project
{
    public function __construct(
        public string $id,
        public string $title,
        public string $url,
    ) {}

    public static function fromLinear(array $data): self
    {
        return new static(
            id: $data['id'],
            title: $data['name'],
            url: $data['url'],
        );
    }

    public static function fromJira(array $data): self
    {
        return new static(
            id: $data['id'],
            title: $data['name'],
            url: $data['self'],
        );
    }
}
