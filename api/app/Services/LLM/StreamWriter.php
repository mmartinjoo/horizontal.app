<?php 

namespace App\Services\LLM;

interface StreamWriter
{
    public function write(string $content): void;
    public function finished(): void;
}