<?php

namespace App\Models;

use App\Enums\Indexing\WorkflowStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class IndexingWorkflowStepItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'data' => 'array',
    ];

    public function indexing_workflow_step()
    {
        return $this->belongsTo(IndexingWorkflowStep::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function user(): HasManyThrough
    {
        return $this->hasManyThrough(User::class, IndexingWorkflowStep::class);
    }

    public static function createForDocument(
        Document $document,
        int $bucketId,
        array $data,
        string $jobId,
    ): self {
        return self::create([
            'indexing_workflow_step_bucket_id' => $bucketId,
            'data' => $data,
            'status' => WorkflowStatus::Processing->value,
            'entity_type' => get_class($document),
            'entity_id' => $document->id,
            'job_id' => $jobId,
            'started_at' => now(),
        ]);
    }

    public function completed(): void
    {
        $this->update([
            'status' => WorkflowStatus::Completed->value,
            'finished_at' => now(),
        ]);
    }

    public function failed(string $errorMessage): void
    {
        $this->update(attributes: [
            'status' => WorkflowStatus::Failed->value,
            'error_message' => $errorMessage,
            'finished_at' => now(),
        ]);
    }

    public function warning(): void
    {
        $this->update(attributes: [
            'status' => WorkflowStatus::Failed->value,
            'finished_at' => now(),
        ]);
    }
}
