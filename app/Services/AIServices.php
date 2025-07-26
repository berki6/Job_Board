<?php

namespace App\Services;

use Gemini\Laravel\Facades\Gemini;

class AIServices
{
    public function generateCoverLetter($job, $user, $customTemplate = null)
    {
        $prompt = $customTemplate ?? $this->defaultPrompt($job, $user);

        $response = Gemini::geminiPro()->generateContent($prompt);

        return $response->text();
    }

    protected function defaultPrompt($job, $user)
    {
        $skills = $user->profile->skills ?? 'Not specified';
        $bio = $user->profile->bio ?? 'Not specified';

        return <<<EOT
                        Write a professional cover letter for this job:

                        Job Title: {$job->title}
                        Description: {$job->description}

                        Candidate Info:
                        Name: {$user->name}
                        Skills: {$skills}
                        Experience: {$bio}

                        Make it concise, polite, and tailored for the job.
                    EOT;
    }
}
