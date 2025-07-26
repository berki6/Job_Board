<?php

namespace App\Services;

use App\Models\Job;
use App\Models\Application;
use App\Models\AutoApplyLog;
use App\Services\AIServices;
use App\Models\User;
use App\Models\Profile;
use App\Models\AutoApplyPreference;
use Illuminate\Support\Facades\DB;
use Exception;

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

        $profile = $user->profile;
        if (!$profile || !$profile->resume_path) {
            AutoApplyLog::create([
                'user_id' => $user->id,
                'status' => 'failed',
                'reason' => 'User profile or resume not found',
            ]);
            return;
        }

        // Fetch jobs based on user preferences
        $jobs = Job::where('status', 'open')
            ->when(
                !$preferences->job_titles && !$preferences->locations && !$preferences->salary_min && !$preferences->salary_max,
                fn($q) => $q,
                fn($q) => $q
                    ->when($preferences->job_titles && !empty($preferences->job_titles), fn($q) => $q->whereIn('title', $preferences->job_titles))
                    ->when($preferences->locations && !empty($preferences->locations), fn($q) => $q->whereIn('location', $preferences->locations))
                    ->when($preferences->salary_min, fn($q) => $q->where('salary', '>=', $preferences->salary_min))
                    ->when($preferences->salary_max, fn($q) => $q->where('salary', '<=', $preferences->salary_max))
                    ->whereDoesntHave('applications', fn($q) => $q->where('user_id', $user->id))
            )
            ->get();

        if ($jobs->isEmpty()) {
            AutoApplyLog::create([
                'user_id' => $user->id,
                'status' => 'no_jobs_found',
                'reason' => 'No jobs matching user preferences'
            ]);
            return;
        }

        $appliedJobs = [];

        foreach ($jobs as $job) {
            try {
                $coverLetter = $this->aiService->generateCoverLetter($job, $user, $preferences->cover_letter_template);

                Application::create([
                    'job_id' => $job->id ?? null,
                    'user_id' => $user->id,
                    'resume_path' => $profile->resume_path,
                    'cover_letter' => $coverLetter,
                    'status' => 'pending'
                ]);

                AutoApplyLog::create([
                    'user_id' => $user->id,
                    'job_id' => $job->id ?? null,
                    'status' => 'success'
                ]);

                $appliedJobs[] = $job->id;

            } catch (Exception $e) {
                AutoApplyLog::create([
                    'user_id' => $user->id,
                    'job_id' => $job->id ?? null,
                    'status' => 'failed',
                    'reason' => $e->getMessage()
                ]);
            }
        }
        // Final summary log
        if (!empty($appliedJobs)) {
            AutoApplyLog::create([
                'user_id' => $user->id,
                'status' => 'completed',
                'reason' => count($appliedJobs) > 0
                    ? 'Auto-apply completed successfully for ' . count($appliedJobs) . ' jobs'
                    : 'No jobs applied successfully'
            ]);
        }
        return $appliedJobs;
    }
}