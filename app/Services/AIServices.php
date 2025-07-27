<?php

namespace App\Services;

use App\Contracts\GeminiClientInterface;
use App\Models\Job;
use App\Models\User;
use Gemini\Laravel\Facades\Gemini;
use Illuminate\Support\Facades\Log;

class AIServices
{
    public function __construct(protected GeminiClientInterface $client)
    {
        Log::info('AIServices initialized.');
    }
    public function generateCoverLetter(Job $job, User $user, ?string $preferences = null): string
    {
        Log::info('Generating cover letter started.', [
            'job_id' => $job->id,
            'job_title' => $job->title,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'preferences' => $preferences,
        ]);

        if (!$user->profile) {
            Log::error('User profile not found when generating cover letter.', ['user_id' => $user->id]);
            throw new \Exception('User profile not found');
        }

        $formattedSalary = '$' . number_format(floatval($job->salary), 2); // Convert to float before formatting

        $prompt = "Generate a professional cover letter for the following job:\n\n" .
            "Job Title: {$job->title}\nLocation: {$job->location}\nSalary: $formattedSalary\n\n" .
            "Applicant: {$user->name}\nResume Path: " . (isset($user->profile->resume_path) ? $user->profile->resume_path : 'Not provided');

        Log::debug('Initial prompt constructed.', ['prompt' => $prompt]);

        // If preferences is a JSON string or array with keys 'cover_letter_template' or 'custom_prompt'
        // Defensive: if $preferences is string, try decode to array
        if (is_string($preferences)) {
            $decodedPreferences = json_decode($preferences, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $preferences = $decodedPreferences;
            }
        }

        if (is_array($preferences)) {
            if (!empty($preferences['cover_letter_template'])) {
                Log::info('Applying cover_letter_template from preferences.');
                $prompt = str_replace('{job_title}', $job->title, $preferences['cover_letter_template']);
                $prompt = str_replace('{location}', $job->location, $prompt);
                Log::debug('Prompt after applying cover_letter_template.', ['prompt' => $prompt]);
            }

            if (!empty($preferences['custom_prompt'])) {
                Log::info('Prepending custom_prompt from preferences.');
                $prompt = $preferences['custom_prompt'] . "\n\n" . $prompt;
                Log::debug('Prompt after prepending custom_prompt.', ['prompt' => $prompt]);
            }
        }

        try {
            $result = $this->client->generateText($prompt);
            Log::info('Cover letter generated successfully.');
            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to generate cover letter.', [
                'exception' => $e->getMessage(),
                'job_id' => $job->id,
                'user_id' => $user->id,
            ]);
            throw $e;
        }
    }

    protected function defaultPrompt($job, $user)
    {
        if (!$user->profile) {
            Log::error('User profile not found when generating default prompt.', ['user_id' => $user->id]);
            throw new \Exception('User profile not found');
        }

        $skills = $user->profile->skills ? implode(', ', $user->profile->skills) : 'Not specified';
        $bio = $user->profile->bio ?? 'Not specified';

        Log::debug('Constructing default prompt.', [
            'job_title' => $job->title,
            'job_description' => $job->description,
            'user_name' => $user->name,
            'user_skills' => $skills,
            'user_bio' => $bio,
        ]);

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
