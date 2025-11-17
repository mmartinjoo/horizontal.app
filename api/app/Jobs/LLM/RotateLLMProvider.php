<?php 

namespace App\Jobs\LLM;

use App\Models\Tenant;
use App\Services\LLM\LLMRotation;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RotateLLMProvider implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
    }

    public function handle(LLMRotation $llmRotation)
    {
        $tenant = tenancy()->tenant;
        if (!$tenant) {
            throw new Exception('RotateLLMProvider: unable to retrieve tenant');
        }

        $currentProvider = $tenant->llm_provider ?? config('llm.default');
        $newProvider = $llmRotation->rotate($currentProvider);
        $tenant->llm_provider = $newProvider;
        $tenant->save();
    }
}