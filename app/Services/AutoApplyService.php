<?php

namespace App\Services;

use App\Models\Job;
use App\Models\Application;
use App\Models\AutoApplyLog;
use App\Services\AIServices;
use Exception;
use Illuminate\Support\Facades\Log;

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
            Log::info('User not premium', ['user_id' => $user->id]);
            return;
        }

        $preferences = $user->autoApplyPreference;
        if (!$preferences || !$preferences->auto_apply_enabled) {
            Log::info('No preferences or auto apply disabled', ['user_id' => $user->id]);
            return;
        }

        $profile = $user->profile;
        if (!$profile || !$profile->resume_path) {
            AutoApplyLog::create([
                'user_id' => $user->id,
                'status' => 'failed',
                'reason' => 'User profile or resume not found',
            ]);
            Log::info('No profile or resume', ['user_id' => $user->id]);
            return;
        }

        $jobTitles = is_string($preferences->job_titles) ? json_decode($preferences->job_titles, true) : $preferences->job_titles;
        $locations = is_string($preferences->locations) ? json_decode($preferences->locations, true) : $preferences->locations;
        $jobTypes = is_string($preferences->job_types) ? json_decode($preferences->job_types, true) : $preferences->job_types;

        $jobsQuery = Job::where('status', 'open')
            ->whereDoesntHave('applications', fn($q) => $q->where('user_id', $user->id));

        if ($jobTitles && !empty(array_filter($jobTitles))) {
            $jobsQuery->whereIn('title', (array) $jobTitles);
        }
        if ($locations && !empty(array_filter($locations))) {
            $jobsQuery->whereIn('location', (array) $locations);
        }
        if ($jobTypes && !empty(array_filter($jobTypes))) {
            $jobsQuery->leftJoin('job_types', 'jobs_listing.job_type_id', '=', 'job_types.id')
                ->whereIn('job_types.name', (array) $jobTypes);
        }
        if ($preferences->salary_min) {
            $jobsQuery->where('salary', '>=', (float) $preferences->salary_min);
        }
        if ($preferences->salary_max) {
            $jobsQuery->where('salary', '<=', (float) $preferences->salary_max);
        }

        $jobs = $jobsQuery->get();
        Log::info('Jobs found', ['count' => $jobs->count(), 'jobs' => $jobs->toArray()]);
        Log::info('Preferences', [
            'job_titles' => $jobTitles,
            'locations' => $locations,
            'job_types' => $jobTypes,
            'salary_min' => $preferences->salary_min,
            'salary_max' => $preferences->salary_max
        ]);

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