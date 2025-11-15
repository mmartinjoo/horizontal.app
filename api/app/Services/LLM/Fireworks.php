<?php

namespace App\Services\LLM;

use Exception;
use Generator;
use GuzzleHttp\Client;
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

    public function stream(string $prompt, StreamWriter $destination, int $maxTokens = 10_000)
    {
        $client = new Client();
        $response = $client->request('POST', 'https://api.fireworks.ai/inference/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $this->model,
                'max_tokens' => $maxTokens,
                'top_p' => 1,
                'top_k' => 40,
                'presence_penalty' => 0,
                'frequency_penalty' => 0,
                'temperature' => 0.1,
                'stream' => true,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
            ],
            'stream' => true,
            'timeout' => 300,
        ]);

        $body = $response->getBody();
        $buffer = '';

        while (!$body->eof()) {
            $chunk = $body->read(1024);
            $buffer .= $chunk;
            
            // Process complete SSE messages
            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $message = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 2);
                
                if (strpos($message, 'data: ') === 0) {
                    $data = substr($message, 6);
                    
                    if ($data === '[DONE]') {
                        $destination->finished();
                        break 2;
                    }
                    
                    $json = json_decode($data, true);
                    if ($json && isset($json['choices'][0]['delta']['content'])) {
                        $content = $json['choices'][0]['delta']['content'];

                        $destination->write($content);
                    }
                }
            }
        }
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
