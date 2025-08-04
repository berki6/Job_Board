<?php

namespace App\Services;

use App\Models\Application;
use App\Models\AutoApplyLog;
use App\Models\Job;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; // Added for DB::raw()

class AutoApplyService
{
    protected $aiService;

    /**
     * AutoApplyService constructor.
     */
    public function __construct(AIServices $aiService)
    {
        $this->aiService = $aiService;
    }

    public function processForUser($user)
    {
        $user = \App\Models\User::with('autoApplyPreference')->find($user->id);

        Log::info('Processing auto-apply for user', ['user_id' => $user->id]);

        if (! $user->subscribed('premium')) {
            Log::info('User not premium', ['user_id' => $user->id]);

            return;
        }
        $preferences = $user->autoApplyPreference;

        if (! $preferences || ! $preferences->auto_apply_enabled) {
            Log::info('No preferences or auto apply disabled', ['user_id' => $user->id]);

            return;
        }

        Log::info('User preferences', ['user_id' => $user->id, 'user_preferences' => $preferences]);

        $jobs = $this->getFilteredJobs($preferences, $user->id);

        Log::info('Filtered jobs for user', ['user_id' => $user->id, 'filtered_jobs' => $jobs->toArray()]);

        $profile = $user->profile;
        if (! $profile || ! $profile->resume_path) {
            AutoApplyLog::create([
                'user_id' => $user->id,
                'status' => 'failed',
                'reason' => 'User profile or resume not found',
            ]);
            Log::info('No profile or resume, skipping user', ['user_id' => $user->id]);

            return;
        }

        Log::info('User passes initial checks', ['user_id' => $user->id]);

        Log::info('Processed preferences', [
            'job_titles' => $preferences->job_titles,
            'locations' => $preferences->locations,
            'job_types' => $preferences->job_types,
            'salary_min' => $preferences->salary_min,
            'salary_max' => $preferences->salary_max,
        ]);

        Log::info('Jobs found', ['count' => $jobs->count(), 'jobs' => $jobs->toArray()]);

        // If no jobs match the user's preferences, log and skip
        if ($jobs->isEmpty()) {
            AutoApplyLog::create([
                'user_id' => $user->id,
                'status' => 'no_jobs_found',
                'reason' => 'No jobs matching user preferences',
            ]);

            return;
        }

        $appliedJobs = [];

        foreach ($jobs as $job) {
            try {
                Log::info('DEBUG: Attempting to generate cover letter', ['job_id' => $job->id, 'user_id' => $user->id]);

                // Wrap potential exception-throwing call inside try-catch
                $coverLetter = null;
                try {
                    $coverLetter = $this->aiService->generateCoverLetter($job, $user, $preferences->cover_letter_template);
                } catch (Exception $e) {
                    Log::error('Exception during cover letter generation', ['message' => $e->getMessage()]);
                    AutoApplyLog::create([
                        'user_id' => $user->id,
                        'job_id' => $job->id,
                        'status' => 'failed',
                        'reason' => $e->getMessage(),
                    ]);

                    // Skip this job, continue with next
                    continue;
                }

                if (empty($coverLetter)) {
                    throw new Exception('Cover letter generation failed or returned empty.');
                }

                Log::info('DEBUG: Cover letter generated, creating application', ['cover_letter' => $coverLetter]);

                $application = Application::factory()->create([
                    'job_id' => $job->id,
                    'user_id' => $user->id,
                    'resume_path' => $profile->resume_path,
                    'cover_letter' => $coverLetter,
                    'status' => 'pending',
                ]);

                Log::info('DEBUG: Application created', ['application_id' => $application->id]);

                AutoApplyLog::create([
                    'user_id' => $user->id,
                    'job_id' => $job->id,
                    'status' => 'success',
                ]);

                $appliedJobs[] = $job->id;

            } catch (Exception $e) {
                Log::error('Unexpected exception caught during auto-apply', ['message' => $e->getMessage()]);
                AutoApplyLog::create([
                    'user_id' => $user->id,
                    'job_id' => $job->id,
                    'status' => 'failed',
                    'reason' => $e->getMessage(),
                ]);
                // Continue processing other jobs
            }
        }

        // Final summary log
        if (! empty($appliedJobs)) {
            AutoApplyLog::create([
                'user_id' => $user->id,
                'status' => 'completed',
                'reason' => count($appliedJobs) > 0
                    ? 'Auto-apply completed successfully for '.count($appliedJobs).' jobs'
                    : 'No jobs applied successfully',
            ]);
        }
        Log::info('Finished processing auto-apply for user', ['user_id' => $user->id]);

        return $appliedJobs;
    }

    /**
     * Get filtered jobs according to all user preferences.
     *
     * @param  object  $preferences  User preferences object containing salary_min, salary_max, job_titles, locations, job_types
     * @param  int|null  $userId  Optional user ID to exclude already applied jobs
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getFilteredJobs($preferences, $userId = null)
    {
        $query = Job::query()->where('is_open', true);

        if ($userId !== null) {
            $query->whereDoesntHave('applications', fn ($q) => $q->where('user_id', $userId));
        }

        $query->whereBetween('salary_min', [$preferences->salary_min, $preferences->salary_max]);

        // Cast job_titles safely to array and filter out empty values
        $jobTitles = is_string($preferences->job_titles)
            ? json_decode($preferences->job_titles, true) ?? [$preferences->job_titles]
            : (array) $preferences->job_titles;

        $jobTitles = array_filter($jobTitles);

        if (! empty($jobTitles)) {
            $query->where(function ($q) use ($jobTitles) {
                foreach ($jobTitles as $title) {
                    $title = strtolower($title);
                    if (! empty($title)) {
                        $q->orWhereRaw('LOWER(title) LIKE ?', ["%{$title}%"]);
                    }
                }
            });
        }

        // Locations filter
        $locations = is_string($preferences->locations)
            ? json_decode($preferences->locations, true) ?? [$preferences->locations]
            : (array) $preferences->locations;
        $locations = array_filter($locations);
        if (! empty($locations)) {
            $query->whereIn('location', $locations);
        }

        // Job types filter by IDs (same logic as before)
        $jobTypes = is_string($preferences->job_types)
            ? json_decode($preferences->job_types, true) ?? [$preferences->job_types]
            : (array) $preferences->job_types;
        $jobTypes = array_filter($jobTypes);
        if (! empty($jobTypes)) {
            $jobTypeIds = \App\Models\JobType::whereIn(DB::raw('LOWER(name)'), array_map('strtolower', $jobTypes))
                ->pluck('id')
                ->toArray();

            if (! empty($jobTypeIds)) {
                $query->whereIn('job_type_id', $jobTypeIds);
            } else {
                $query->whereRaw('0 = 1'); // forces zero results if no matching job types
            }
        }

        // Select only needed columns (adjust as needed)
        $query->select('jobs_listing.*');

        return $query->get();
    }
}
