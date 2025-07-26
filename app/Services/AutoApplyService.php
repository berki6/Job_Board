<?php

namespace App\Services;

use App\Models\Job;
use App\Models\Application;
use App\Models\AutoApplyLog;
use App\Services\AIServices;
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
        if (!$user->subscribed('premium')) {
            return;
        }

        $preferences = $user->autoApplyPreference;
        if (!$preferences || !$preferences->auto_apply_enabled) {
            return;
        }

        $profile = $user->profile;
        if (!$profile || !$profile->resume_path) {
            AutoApplyLog::create([
                'user_id' => $user->id,
                'status' => 'failed',
                'reason' => 'User profile or resume not found',
            ]);
            return;
        }

        // Decode JSON fields
        $jobTitles = is_string($preferences->job_titles) ? json_decode($preferences->job_titles, true) : $preferences->job_titles;
        $locations = is_string($preferences->locations) ? json_decode($preferences->locations, true) : $preferences->locations;
        $jobTypes = is_string($preferences->job_types) ? json_decode($preferences->job_types, true) : $preferences->job_types;

        $jobsQuery = Job::where('status', 'open')
            ->whereDoesntHave('applications', fn($q) => $q->where('user_id', $user->id));

        // Apply filters only if preferences are set and non-empty
        if ($jobTitles && !empty(array_filter($jobTitles))) {
            $jobsQuery->whereIn('title', (array) $jobTitles);
        }
        if ($locations && !empty(array_filter($locations))) {
            $jobsQuery->whereIn('location', (array) $locations);
        }
        if ($jobTypes && !empty(array_filter($jobTypes))) {
            $jobsQuery->whereIn('type', (array) $jobTypes); // Assumes 'type' column in jobs table
        }
        if ($preferences->salary_min) {
            $jobsQuery->where('salary', '>=', $preferences->salary_min);
        }
        if ($preferences->salary_max) {
            $jobsQuery->where('salary', '<=', $preferences->salary_max);
        }

        $jobs = $jobsQuery->get();

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
                    'job_id' => $job->id,
                    'user_id' => $user->id,
                    'resume_path' => $profile->resume_path,
                    'cover_letter' => $coverLetter,
                    'status' => 'pending'
                ]);
                AutoApplyLog::create([
                    'user_id' => $user->id,
                    'job_id' => $job->id,
                    'status' => 'success'
                ]);
                $appliedJobs[] = $job->id;
            } catch (Exception $e) {
                AutoApplyLog::create([
                    'user_id' => $user->id,
                    'job_id' => $job->id,
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