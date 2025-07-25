<?php

namespace App\Services;

use Google\GenerativeAI\Client;

class AIService
{
    protected $client;

    public function __construct()
    {
        $this->client = new Client(['apiKey' => env('GEMINI_API_KEY')]);
    }

    public function generateCoverLetter($job, $user, $customTemplate = null)
    {
        $prompt = $customTemplate ?? $this->defaultPrompt($job, $user);

        $response = $this->client->models->generateContent([
            'model' => 'gemini-pro',
            'contents' => [['parts' => [['text' => $prompt]]]]
        ]);

        return $response['candidates'][0]['content']['parts'][0]['text'] ?? '';
    }

    protected function defaultPrompt($job, $user)
    {
        return "Write a professional cover letter for this job:

                Job Title: {{$job->title}}
                Description: {{$job->description}}

                Candidate Info:
                Name: {{$user->name}}
                Skills: {{$user->profile->skills ?? 'Not specified'}}
                Experience: {{$user->profile->bio ?? 'Not specified'}}
                Make it concise, polite, and tailored for the job.
                ";
    }
}
