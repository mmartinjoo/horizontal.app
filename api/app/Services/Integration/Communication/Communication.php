<?php

namespace App\Services\Integration\Communication;

use App\Services\Integration\Communication\DataTransferObjects\Channel;
use App\Services\Integration\Communication\DataTransferObjects\Message;
use Illuminate\Support\LazyCollection;

interface Communication
{
    /**
     * @return LazyCollection<Message>
     */
    public function messages(Channel $channel): LazyCollection;

    /**
     * @return LazyCollection<Message>
     */
    public function threads(Channel $channel): LazyCollection;
}