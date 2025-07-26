<?php

namespace App\Contracts;

interface GeminiClientInterface
{
    public function generateText(string $prompt): string;
}
