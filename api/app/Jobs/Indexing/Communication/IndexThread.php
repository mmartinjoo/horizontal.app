<?php

namespace App\Jobs\Indexing\Communication;

use App\Models\Document;
use App\Models\Participant;
use App\Services\Integration\Communication\DataTransferObjects\Message;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class IndexThread implements ShouldQueue
{
    use Queueable;

    public function __construct(private Message $thread)
    {
    }

    public function handle()
    {
        IndexMessage::dispatchSync($this->thread);
        $document = Document::query()
            ->where('source_type', 'slack')
            ->where('source_id', $this->thread->externalId)
            ->firstOrFail();

        /** @var User $mentionedUser */
        foreach ($this->thread->mentions as $mentionedUser) {
            $p = Participant::getOrCreate($mentionedUser->realName);
            $document->participants()->attach($p->id, [
                'context' => 'mentioned',
            ]);
        }

        /** @var Message $reply */
        foreach ($this->thread->replies as $reply) {
            $p = Participant::getOrCreate($reply->author->realName);
            $comment = $document->comments()->create([
                'author_id' => $p->id,
                'body' => $reply->message,
                'commented_at' => now(),
                'comment_id' => $reply->externalId,
                'metadata' => $reply,
                'commented_at' => $reply->createdAt,
            ]);

            /** @var User $mentionedUser */
            foreach ($reply->mentions as $mentionedUser) {
                $p = Participant::getOrCreate($mentionedUser->realName);
                $comment->participants()->attach($p->id, [
                    'context' => 'mentioned',
                ]);
            }
        }
    }
}
