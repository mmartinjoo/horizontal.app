<?php

namespace App\Jobs\Indexing\Communication\Slack;

use App\Models\Document;
use App\Services\Integration\Communication\Slack\Slack;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;


class UpdateSlackMessageLinks implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
    }

    public function handle(Slack $slack): void
    {
        $messages = Document::query()
            ->select('id', 'metadata', 'source_id')
            ->where('source_type', 'slack')
            ->whereNull('source_url')
            ->limit(100)
            ->get();
            
        foreach ($messages as $message) {
            $channelID = Arr::get($message, 'metadata.channel.externalId');
            if (!$channelID) {
                continue;
            }
            $link = $slack->permalink($channelID, $message->source_id);
            $message->update([
                'source_url' => $link,
            ]);
        }
    }
}
