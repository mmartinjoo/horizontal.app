<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class GraphBuilder
{
    public function __construct(private string $baseUrl)
    {
    }

    public function build(): bool
    {
        $response = Http::post($this->baseUrl . '/api/build')
            ->throw();

        return $response->status() === Response::HTTP_ACCEPTED;
    }
}
