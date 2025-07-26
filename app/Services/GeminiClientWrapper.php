<?php

namespace App\Services;

use Gemini\Laravel\Facades\Gemini;
use App\Contracts\GeminiClientInterface;

class GeminiClientWrapper implements GeminiClientInterface
{
    public function generateText(string $prompt): string
    {
        $response = Gemini::geminiPro()->generateContent($prompt);
        return $response->text() ?? '';
    }
}
