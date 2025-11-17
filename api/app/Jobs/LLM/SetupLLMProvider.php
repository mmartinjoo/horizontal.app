<?php 

namespace App\Jobs\LLM;

use App\Services\LLM\LLMRotation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Stancl\Tenancy\Contracts\TenantWithDatabase;

class SetupLLMProvider implements ShouldQueue
{
    use Queueable;

    protected TenantWithDatabase $tenant;

    public function __construct(TenantWithDatabase $tenant)
    {
        $this->tenant = $tenant;
    }

    public function handle(LLMRotation $llmRotation)
    {
        $provider = $llmRotation->getRandomProvider();
        $this->tenant->llm_provider = $provider;
        $this->tenant->save();
    }
}