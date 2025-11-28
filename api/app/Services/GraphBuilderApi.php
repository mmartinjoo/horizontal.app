<?php

namespace App\Services;

use App\Models\IndexingWorkflowStep;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class GraphBuilderApi
{
     public function __construct(
        private string $baseUrl,
    ) {}

    public function buildKG(IndexingWorkflowStep $workflowStep): bool
    {
        $response = Http::post($this->baseUrl . '/api/build', [
            'tenant_id' => tenancy()->tenant->id,
            'workflow_step_id' => $workflowStep->id,
        ])
            ->throw();

        return $response->status() === Response::HTTP_ACCEPTED;
    }

    public function getQueueSize(): int
    {
        $data = Http::get($this->baseUrl . '/api/queue-size')
            ->throw()
            ->json();

        return $data['size'];
    }
}