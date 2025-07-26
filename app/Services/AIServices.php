<?php

namespace App\Services;

use App\Contracts\GeminiClientInterface;
use App\Models\Job;
use App\Models\User;
use Gemini\Laravel\Facades\Gemini;

class AIServices
{
    public function __construct(protected GeminiClientInterface $client)
    {
    }
    public function generateCoverLetter(Job $job, User $user, ?string $preferences = null): string
    {
        $formattedSalary = '$' . number_format(floatval($job->salary), 2); // Convert to float before formatting
        $prompt = "Generate a professional cover letter for the following job:\n\n" .
            "Job Title: {$job->title}\nLocation: {$job->location}\nSalary: $formattedSalary\n\n" .
            "Applicant: {$user->name}\nResume Path: " . (isset($user->profile->resume_path) ? $user->profile->resume_path : 'Not provided');
        if ($preferences['cover_letter_template'] ?? false) {
            $prompt = str_replace('{job_title}', $job->title, $preferences['cover_letter_template']);
            $prompt = str_replace('{location}', $job->location, $prompt);
        }


        if (!empty($preferences['custom_prompt'])) {
            $prompt = $preferences['custom_prompt'] . "\n\n" . $prompt;
        }

        return $this->client->generateText($prompt);
    }

    protected function defaultPrompt($job, $user)
    {
        // Ensure user has a profile before accessing skills
        if (!$user->profile) {
            throw new \Exception('User profile not found');
        }

        $skills = $user->profile->skills ? implode(', ', $user->profile->skills) : 'Not specified';
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
