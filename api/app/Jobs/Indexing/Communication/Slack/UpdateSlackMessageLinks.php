<?php

namespace App\Jobs\Indexing\Communication\Slack;

use App\Models\Document;
use App\Models\Tenant;
use App\Services\Integration\Communication\Slack\Slack;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;


class UpdateSlackMessageLinks implements ShouldQueue
{
    use Queueable;

    public function __construct(private Tenant $tenant)
    {
    }

    public function handle(Slack $slack): void
    {
        tenancy()->initialize($this->tenant);

        $messages = Document::query()
            ->select('id', 'metadata', 'source_id')
            ->where('source', 'slack')
            ->where('source_type', 'message')
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
