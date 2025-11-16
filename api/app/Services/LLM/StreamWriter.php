<?php 

namespace App\Services\LLM;

interface StreamWriter
{
    public function write(string $content): void;
    public function finished(): void;
    public function recordTokenUsage(int $inputTokens, int $outputTokens): void;
}