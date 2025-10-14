<?php

namespace App\Services\Integration\Communication\DataTransferObjects;

use Illuminate\Support\Collection;

class Thread
{
    public function __construct(
        public string $externalId,
        public string $externalUserId,
        /** @var Collection<Message> */
        public Collection $messages,
    )
    {
    }
}
