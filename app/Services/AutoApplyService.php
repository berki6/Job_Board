<?php

namespace App\Services;

use App\Models\Job;
use App\Models\Application;
use App\Models\AutoApplyLog;

class AutoApplyService
{
    protected $aiService;

    public function __construct(AIService $aiService)
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
            ->when($preferences->job_titles, fn($q) => $q->whereIn('title', $preferences->job_titles))
            ->when($preferences->locations, fn($q) => $q->whereIn('location', $preferences->locations))
            ->get();

        foreach ($jobs as $job) {
            if (Application::where('job_id', $job->id)->where('user_id', $user->id)->exists())
                continue;

            try {
                $coverLetter = $this->aiService->generateCoverLetter($job, $user, $preferences->cover_letter_template);
                Application::create([
                    'job_id' => $job->id,
                    'user_id' => $user->id,
                    'resume_path' => $user->profile->resume_path,
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
