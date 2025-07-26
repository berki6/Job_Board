<?php

namespace App\Services;

use App\Models\Job;
use App\Models\Application;
use App\Models\AutoApplyLog;
use App\Services\AIServices;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB; // Added for DB::raw()

class AutoApplyService
{
    protected $aiService;
    /**
     * AutoApplyService constructor.
     *
     * @param AIServices $aiService
     */

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

        // Use array casting to handle both JSON strings and arrays gracefully
        $jobTitles = is_string($preferences->job_titles) ? json_decode($preferences->job_titles, true) ?? [] : (array) ($preferences->job_titles ?? []);
        $locations = is_string($preferences->locations) ? json_decode($preferences->locations, true) ?? [] : (array) ($preferences->locations ?? []);
        $jobTypes = is_string($preferences->job_types) ? json_decode($preferences->job_types, true) ?? [] : (array) ($preferences->job_types ?? []);


        Log::info('Raw preferences', [
            'job_titles' => $preferences->job_titles,
            'locations' => $preferences->locations,
            'job_types' => $preferences->job_types,
        ]);
        Log::info('Processed preferences', [
            'job_titles' => $jobTitles,
            'locations' => $locations,
            'job_types' => $jobTypes,
            'salary_min' => $preferences->salary_min,
            'salary_max' => $preferences->salary_max
        ]);


        $jobsQuery = Job::where('status', 'open')
            ->whereDoesntHave('applications', fn($q) => $q->where('user_id', $user->id));
        Log::info($jobsQuery->toSql(), $jobsQuery->getBindings());
        if ($jobTitles && !empty(array_filter((array) $jobTitles))) {
            $jobsQuery->where(function ($query) use ($jobTitles) {
                foreach ((array) $jobTitles as $title) {
                    if (!empty($title)) {
                        $query->orWhere('title', 'LIKE', "%{$title}%");
                    }
                }
            });
        }
        if ($locations && !empty(array_filter((array) $locations))) {
            $jobsQuery->whereIn('location', (array) $locations);
        }
        if ($jobTypes && !empty(array_filter((array) $jobTypes))) {
            $jobsQuery->leftJoin('job_types', 'jobs_listing.job_type_id', '=', 'job_types.id')
                ->where(function ($query) use ($jobTypes) {
                    // FIX: Replaced problematic whereRaw with whereIn for compatibility and security.
                    // The original whereRaw would not bind the array correctly for an IN clause.
                    $query->whereIn(DB::raw('LOWER(job_types.name)'), array_map('strtolower', (array) $jobTypes))
                        ->orWhereNull('jobs_listing.job_type_id');
                });
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

        // FIX: The condition was inverted. It should check if the collection IS EMPTY, not if it's NOT empty.
        // This was the primary bug causing tests to fail, as it would exit before applying for any jobs found.
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
                Log::info('DEBUG: Attempting to generate cover letter', ['job_id' => $job->id, 'user_id' => $user->id]);
                $coverLetter = $this->aiService->generateCoverLetter($job, $user, $preferences->cover_letter_template);
                // Check if cover letter generation was successful
                if (empty($coverLetter)) {
                    throw new Exception('Cover letter generation failed or returned empty.');
                }
                Log::info('DEBUG: Cover letter generated, creating application', ['cover_letter' => $coverLetter]);

                $application = Application::factory()->create([
                    'job_id' => $job->id,
                    'user_id' => $user->id,
                    'resume_path' => $profile->resume_path,
                    'cover_letter' => $coverLetter,
                    'status' => 'pending'
                ]);
                Log::info('DEBUG: Application created', ['application_id' => $application->id]);

                AutoApplyLog::create([
                    'user_id' => $user->id,
                    'job_id' => $job->id,
                    'status' => 'success'
                ]);
                $appliedJobs[] = $job->id;
            } catch (Exception $e) {
                Log::info('DEBUG: Exception caught', ['message' => $e->getMessage()]);
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
        } else {
            AutoApplyLog::create([
                'user_id' => $user->id,
                'status' => 'completed',
                'reason' => 'No jobs applied successfully'
            ]);
        }
        return $appliedJobs;
    }
}