<?php

namespace App\Services\LLM;

use Exception;
use Illuminate\Support\Facades\Http;

class Fireworks extends LLM implements Embedder
{
    public function completion(string $prompt, $maxTokens = 1024): string
    {
        $res = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey,
        ])
            ->timeout(300)
            ->post('https://api.fireworks.ai/inference/v1/completions', [
                    'model' => $this->model,
                    'prompt' => $prompt,
                    'max_tokens' => $maxTokens,
                    'temperature' => 0.4,
                ],
            )
            ->throw()
            ->json();

        if (empty($res['choices'][0]['text'])) {
            throw new Exception('Fireworks: No completion found: '.json_encode($res));
        }

        return $this->sanitizeJSON($res['choices'][0]['text']);
    }

    public function createEmbedding(string $text): array
    {
        $res = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey,
        ])
            ->timeout(300)
            ->post('https://api.fireworks.ai/inference/v1/embeddings', [
                'model' => 'nomic-ai/nomic-embed-text-v1.5',
                'dimensions' => 768,
                'input' => $text,
            ],
            )
            ->throw()
            ->json();

        if (!isset($res['data'][0])) {
            throw new Exception('Fireworks: No embedding created: '.json_encode($res));
        }

        return $res['data'][0]['embedding'];
    }
}
