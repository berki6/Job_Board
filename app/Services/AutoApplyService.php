<?php

namespace App\Services;

use App\Models\Job;
use App\Models\Application;
use App\Models\AutoApplyLog;
use App\Services\AIServices;

class AutoApplyService
{
    protected $aiService;

    public function __construct(AIServices $aiService)
    {
        $this->aiService = $aiService;
    }

    public function processForUser($user)
    {
        if (!$user->subscribed('premium'))
            return;

        $preferences = $user->autoApplyPreference;
        if (!$preferences || !$preferences->auto_apply_enabled)
            return;

        $jobs = Job::where('status', 'open')
            ->when($preferences->job_titles && !empty($preferences->job_titles), fn($q) => $q->whereIn('title', $preferences->job_titles))
            ->when($preferences->locations && !empty($preferences->locations), fn($q) => $q->whereIn('location', $preferences->locations))
            ->when($preferences->salary_min, fn($q) => $q->where('salary', '>=', $preferences->salary_min))
            ->when($preferences->salary_max, fn($q) => $q->where('salary', '<=', $preferences->salary_max))
            ->get();

        foreach ($jobs as $job) {
            if (Application::where('job_id', $job->id)->where('user_id', $user->id)->exists())
                continue;

            try {
                // Skip if user doesn't have a profile or resume
                if (!$user->profile || !$user->profile->resume_path) {
                    AutoApplyLog::create([
                        'user_id' => $user->id,
                        'job_id' => $job->id,
                        'status' => 'failed',
                        'reason' => 'User profile or resume not found'
                    ]);
                    continue;
                }

                $coverLetter = $this->aiService->generateCoverLetter($job, $user, $preferences->cover_letter_template);
                Application::create([
                    'job_id' => $job->id,
                    'user_id' => $user->id,
                    'resume_path' => $user->profile->resume_path,
                    'cover_letter' => $coverLetter,
                    'status' => 'pending'
                ]);

                AutoApplyLog::create([
                    'user_id' => $user->id,
                    'job_id' => $job->id,
                    'status' => 'success'
                ]);
            } catch (\Exception $e) {
                AutoApplyLog::create([
                    'user_id' => $user->id,
                    'job_id' => $job->id,
                    'status' => 'failed',
                    'reason' => $e->getMessage()
                ]);
            }
        }
    }
}
