<?php

namespace App\Jobs\Indexing\Communication\Slack;

use App\Jobs\Indexing\Communication\IndexMessage;
use App\Jobs\Indexing\Communication\IndexThread;
use App\Services\Integration\Communication\Slack\Slack;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class IndexSlack implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
    }

    public function handle(Slack $slack)
    {
        $channels = $slack->channels();
        foreach ($channels as $channel) {
            $messages = $slack->messages($channel);
            foreach ($messages as $message) {
                IndexMessage::dispatch($message);
            }

            $threads = $slack->threads($channel);
            foreach ($threads as $thread) {
                IndexThread::dispatch($thread);
            }
        }
    }
}
