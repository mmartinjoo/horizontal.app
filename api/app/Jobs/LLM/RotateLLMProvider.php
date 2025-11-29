<?php 

namespace App\Jobs\LLM;

use App\Models\Tenant;
use App\Services\LLM\LLMRotation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use League\OAuth1\Client\Server\Xing;

class RotateLLMProvider implements ShouldQueue
{
    use Queueable;

    public function __construct(private Tenant $tenant)
    {
    }

    public function handle(LLMRotation $llmRotation)
    {
        logger()->info('---- Rotate LLM provider ----');
        logger()->info('tenant BEFORE update');
        logger()->info($this->tenant);
        $currentProvider = $tenant->llm_provider ?? config('llm.default');
        logger()->info('current provider');
        logger()->info($currentProvider);
        $newProvider = $llmRotation->rotate($currentProvider);
        logger()->info('new provider');
        logger()->info($newProvider);
        $this->tenant->llm_provider = $newProvider;
        $this->tenant->save();
        logger()->info('tenant AFTER update');
        logger()->info($this->tenant);
    }
}