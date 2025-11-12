<?php

namespace App\Services\LLM;

use Exception;
use Illuminate\Support\Facades\Http;

class Fireworks extends LLM implements Embedder
{
    use HasEmbeddingCache;

    public function completion(string $prompt, $maxTokens = 4999): string
    {
        $res = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
        ])
            ->timeout(300)
            ->post('https://api.fireworks.ai/inference/v1/chat/completions', [
                    'model' => $this->model,
                    'max_tokens' => $maxTokens,
                    "top_p" => 1,
                    "top_k" => 40,
                    "presence_penalty" => 0,
                    "frequency_penalty" => 0,
                    "temperature" => 0.1,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                ],
            )
            ->throw()
            ->json();

        if (empty($res['choices'][0]['message']['content'])) {
            throw new Exception('Fireworks: No completion found: '.json_encode($res));
        }

        return $this->sanitizeJSON($res['choices'][0]['message']['content']);
    }

    protected function createEmbeddingWithoutCache(string $text): array
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
