<?php

namespace App\Services;

use App\Contracts\GeminiClientInterface;
use Gemini\Laravel\Facades\Gemini;

class GeminiClientWrapper implements GeminiClientInterface
{
    public function generateText(string $prompt): string
    {
        $response = Gemini::geminiPro()->generateContent($prompt);

        return $response->text() ?? '';
    }
}
