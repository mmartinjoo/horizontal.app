<?php 

namespace App\Jobs\LLM;

use App\Models\Tenant;
use App\Services\LLM\LLMRotation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RotateLLMProvider implements ShouldQueue
{
    use Queueable;

    public function __construct(private Tenant $tenant)
    {
    }

    public function handle(LLMRotation $llmRotation)
    {
        tenancy()->initialize($this->tenant);
        $currentProvider = $tenant->llm_provider ?? config('llm.default');
        $newProvider = $llmRotation->rotate($currentProvider);
        $this->tenant->llm_provider = $newProvider;
        $this->tenant->save();
    }
}