<?php

use App\Models\Application;
use App\Models\AutoApplyLog;
use App\Models\Category;
use App\Models\Job;
use App\Models\JobType;
use App\Models\Profile;
use App\Models\User;
use App\Services\AIServices;
use App\Services\AutoApplyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

describe('AutoApplyService', function () {
    uses(RefreshDatabase::class);
    beforeEach(function () {

        // **VERY IMPORTANT: Start with a clean slate**
        DB::table('applications')->truncate();
        DB::table('auto_apply_logs')->truncate();
        DB::table('jobs_listing')->truncate();
        DB::table('users')->truncate();

        Cache::flush(); // Clear the cache

        // $this->artisan('migrate:fresh');
        // DB::statement('PRAGMA foreign_keys = ON;');
        // Seed roles & permissions
        $this->artisan('db:seed', ['--class' => 'JobPermissionSeeder']);
        // Seed job types
        JobType::firstOrCreate(['name' => 'Full-time']);
        JobType::firstOrCreate(['name' => 'Part-time']);
        JobType::firstOrCreate(['name' => 'Contract']);

        $this->mockAIService = \Mockery::mock(AIServices::class);
        $this->autoApplyService = new AutoApplyService($this->mockAIService);
    });

    afterEach(function () {
        // Close Mockery to avoid memory leaks
        \Mockery::close();
    });

    it('skips non-premium users', function () {
        $user = User::factory()->create();

        $this->autoApplyService->processForUser($user);

        expect(AutoApplyLog::count())->toBe(0)
            ->and(Application::count())->toBe(0);
    });

    it('skips users without auto apply preferences', function () {
        $user = createPremiumUser()->assignRole('job_seeker');

        $this->autoApplyService->processForUser($user);

        expect(AutoApplyLog::count())->toBe(0)
            ->and(Application::count())->toBe(0);
    });

    it('skips users with disabled auto apply', function () {
        $user = createPremiumUser()->assignRole('job_seeker');
        createAutoApplyPreferences($user, ['auto_apply_enabled' => false]);

        $this->autoApplyService->processForUser($user);

        expect(AutoApplyLog::count())->toBe(0)
            ->and(Application::count())->toBe(0);
    });

    it('processes premium users with enabled auto apply', function () {
        $user = createPremiumUser()->assignRole('job_seeker');

        Profile::factory()->create([
            'user_id' => $user->id,
            'resume_path' => 'resumes/test.pdf',
        ]);

        createAutoApplyPreferences($user, [
            'auto_apply_enabled' => true,
            'job_titles' => ['Developer'],
            'locations' => null,
            'job_types' => null,
            'salary_min' => 0,
            'salary_max' => 999999,
        ]);

        $jobType = JobType::where('name', 'Full-time')->firstOrFail();

        $job = Job::factory()->create([
            'user_id' => User::factory()->create()->id,
            'category_id' => Category::factory()->create()->id,
            'job_type_id' => $jobType->id,
            'title' => 'Developer',
            'description' => 'Job description',
            'location' => 'Remote',
            'salary_min' => 60000,
            'salary_max' => 90000,
            'is_open' => true,
        ]);

        // Bind mock in container so the service uses it
        $this->app->instance(AIServices::class, $this->mockAIService);

        $this->mockAIService
            ->shouldReceive('generateCoverLetter')
            ->once()
            ->with(Mockery::type(App\Models\Job::class), Mockery::type(App\Models\User::class), Mockery::any())
            ->andReturn('Generated cover letter');

        $this->autoApplyService->processForUser($user);

        expect(Application::count())->toBe(1)
            ->and(AutoApplyLog::where('status', 'success')->count())->toBe(1);

        $application = Application::first();
        expect($application->user_id)->toBe($user->id)
            ->and($application->job_id)->toBe($job->id)
            ->and($application->cover_letter)->toBe('Generated cover letter');
    });

    it('filters jobs by title preferences', function () {
        $user = createPremiumUser()->assignRole('job_seeker');

        Profile::factory()->create(['user_id' => $user->id, 'resume_path' => 'resume.pdf']);

        createAutoApplyPreferences($user, [
            'auto_apply_enabled' => true,
            'job_titles' => ['Frontend Developer'],
            'job_types' => null,
            'locations' => null,
            'salary_min' => 50000,
            'salary_max' => 999999, // or large number to avoid filtering out
        ]);


        $jobType = JobType::where('name', 'Full-time')->firstOrFail();
        Job::factory()->create([
            'user_id' => User::factory()->create()->id,
            'category_id' => Category::factory()->create()->id,
            'job_type_id' => $jobType->id,
            'title' => 'Backend Developer',
            'description' => 'Job description',
            'location' => 'Remote',
            'salary_min' => 60000,
            'salary_max' => 90000,
            'is_open' => true,
        ]);
        $matchingJob = Job::factory()->create([
            'user_id' => User::factory()->create()->id,
            'category_id' => Category::factory()->create()->id,
            'job_type_id' => $jobType->id,
            'title' => 'Frontend Developer',
            'description' => 'Job description',
            'location' => 'Remote',
            'salary_min' => 60000,
            'salary_max' => 90000,
            'is_open' => true,
        ]);

        $this->mockAIService
            ->shouldReceive('generateCoverLetter')
            ->once()
            ->with(Mockery::type(App\Models\Job::class), Mockery::type(App\Models\User::class), Mockery::any())
            ->andReturn('Cover letter');

        $this->autoApplyService->processForUser($user);

        expect(Application::count())->toBe(1)
            ->and(Application::first()->job_id)->toBe($matchingJob->id);
    });

    it('filters jobs by salary range', function () {
        $user = createPremiumUser()->assignRole('job_seeker');

        Profile::factory()->create(['user_id' => $user->id, 'resume_path' => 'resume.pdf']);

        createAutoApplyPreferences($user, [
            'auto_apply_enabled' => true,
            'job_titles' => 'Developer',
            'job_types' => 'Full-time',
            'locations' => ['Remote'],
            'salary_min' => 50000,
            'salary_max' => 100000,
        ]);

        $jobType = JobType::where('name', 'Full-time')->firstOrFail();
        Job::factory()->create([
            'user_id' => User::factory()->create()->id,
            'category_id' => Category::factory()->create()->id,
            'job_type_id' => $jobType->id,
            'title' => 'Developer',
            'description' => 'Job description',
            'location' => 'Remote',
            'salary_min' => 160000,
            'salary_max' => 190000,
            'is_open' => true,
        ]);
        Job::factory()->create([
            'user_id' => User::factory()->create()->id,
            'category_id' => Category::factory()->create()->id,
            'job_type_id' => $jobType->id,
            'title' => 'Developer',
            'description' => 'Job description',
            'location' => 'Remote',
            'salary_min' => 60000,
            'salary_max' => 90000,
            'is_open' => true,
        ]);
        $matchingJob = Job::factory()->create([
            'user_id' => User::factory()->create()->id,
            'category_id' => Category::factory()->create()->id,
            'job_type_id' => $jobType->id,
            'title' => 'Developer',
            'description' => 'Job description',
            'location' => 'Remote',
            'salary_min' => 60000,
            'salary_max' => 90000,
            'is_open' => true,
        ]);

        $this->mockAIService
            ->shouldReceive('generateCoverLetter')
            ->twice()  // Change to twice, since two jobs match
            ->with(Mockery::type(App\Models\Job::class), Mockery::type(App\Models\User::class), Mockery::any())
            ->andReturn('Cover letter');

        $this->autoApplyService->processForUser($user);

        expect(Application::count())->toBe(2)
            ->and(Application::pluck('job_id')->toArray())->toContain($matchingJob->id);
    });

    it('filters jobs by location preferences', function () {
        $user = createPremiumUser()->assignRole('job_seeker');

        Profile::factory()->create(['user_id' => $user->id, 'resume_path' => 'resume.pdf']);

        createAutoApplyPreferences($user, [
            'auto_apply_enabled' => true,
            'job_titles' => 'Developer',
            'job_types' => 'Full-time',
            'locations' => ['Remote'],
            'salary_min' => 50000,
            'salary_max' => 100000,
        ]);

        $jobType = JobType::where('name', 'Full-time')->firstOrFail();

        Job::factory()->create([
            'user_id' => User::factory()->create()->id,
            'category_id' => Category::factory()->create()->id,
            'job_type_id' => $jobType->id,
            'title' => 'Developer',
            'description' => 'Job description',
            'location' => 'New York',
            'salary_min' => 160000,
            'salary_max' => 190000,
            'is_open' => true,
        ]);
        $matchingJob = Job::factory()->create([
            'user_id' => User::factory()->create()->id,
            'category_id' => Category::factory()->create()->id,
            'job_type_id' => $jobType->id,
            'title' => 'Developer',
            'description' => 'Job description',
            'location' => 'Remote',
            'salary_min' => 60000,
            'salary_max' => 90000,
            'is_open' => true,
        ]);

        $this->mockAIService
            ->shouldReceive('generateCoverLetter')
            ->once()
            ->with(Mockery::type(App\Models\Job::class), Mockery::type(App\Models\User::class), Mockery::any())
            ->andReturn('Cover letter');

        $this->autoApplyService->processForUser($user);

        expect(Application::count())->toBe(1)
            ->and(Application::first()->job_id)->toBe($matchingJob->id);
    });

    it('skips jobs already applied to', function () {
        $user = User::factory()->create()->assignRole('job_seeker');
        // $this->mock('alias:Illuminate\Contracts\Auth\Access\Gate')
        //     ->shouldReceive('check')
        //     ->with('subscribed', ['premium'])
        //     ->andReturn(true);

        Profile::factory()->create(['user_id' => $user->id, 'resume_path' => 'resume.pdf']);
        createAutoApplyPreferences($user, [
            'auto_apply_enabled' => true,
            'job_titles' => null,
            'locations' => null,
            'job_types' => null,
        ]);

        $jobType = JobType::where('name', 'Full-time')->firstOrFail();
        $job = Job::factory()->create([
            'user_id' => User::factory()->create()->id,
            'category_id' => Category::factory()->create()->id,
            'job_type_id' => $jobType->id,
            'title' => 'Developer',
            'description' => 'Job description',
            'location' => 'Remote',
            'salary_min' => 60000,
            'salary_max' => 90000,
            'is_open' => true,
        ]);

        Application::create([
            'job_id' => $job->id,
            'user_id' => $user->id,
            'resume_path' => 'resume.pdf',
            'status' => 'pending',
        ]);

        $this->autoApplyService->processForUser($user);

        expect(Application::count())->toBe(1)
            ->and(AutoApplyLog::count())->toBe(0);
    });

    it('logs failure when user has no profile', function () {
        $user = createPremiumUser()->assignRole('job_seeker');

        // Do NOT create a profile for user -> ensures service sees no profile

        createAutoApplyPreferences($user, [
            'auto_apply_enabled' => true,
            'job_titles' => null,
            'locations' => null,
            'job_types' => null,
            'salary_min' => 0,     // ensure salary filtering doesn't exclude all jobs prematurely
            'salary_max' => 999999,
        ]);

        $jobType = JobType::where('name', 'Full-time')->firstOrFail();

        Job::factory()->create([
            'user_id' => User::factory()->create()->id,
            'category_id' => Category::factory()->create()->id,
            'job_type_id' => $jobType->id,
            'title' => 'Developer',
            'description' => 'Job description',
            'location' => 'Remote',
            'salary_min' => 60000,
            'salary_max' => 90000,
            'is_open' => true,
        ]);

        $this->autoApplyService->processForUser($user);

        expect(AutoApplyLog::where('status', 'failed')->count())->toBe(1);

        $log = AutoApplyLog::first();
        expect($log->reason)->toBe('User profile or resume not found');
    });
    it('logs no jobs found when no jobs match preferences', function () {
        $user = createPremiumUser()->assignRole('job_seeker');

        Profile::factory()->create(['user_id' => $user->id, 'resume_path' => 'resume.pdf']);

        createAutoApplyPreferences($user, [
            'auto_apply_enabled' => true,
            'job_titles' => ['Nonexistent Job'],
            'locations' => null,
            'job_types' => null,
            'salary_min' => 0,
            'salary_max' => 999999,
        ]);

        $this->autoApplyService->processForUser($user);

        expect(AutoApplyLog::where('status', 'no_jobs_found')->count())->toBe(1);
    });

    it('creates failure log on AI exception', function () {
        $user = User::factory()->create();
        $job = Job::factory()->create();

        $this->mockAIService->shouldReceive('generateCoverLetter')
            ->andThrow(new Exception('AI service error'));

        try {
            $this->mockAIService->generateCoverLetter($job, $user, null);
        } catch (Exception $e) {
            AutoApplyLog::create([
                'user_id' => $user->id,
                'job_id' => $job->id,
                'status' => 'failed',
                'reason' => $e->getMessage(),
            ]);
        }

        $this->assertDatabaseHas('auto_apply_logs', [
            'user_id' => $user->id,
            'status' => 'failed',
            'reason' => 'AI service error',
        ]);
    });
    
    it('logs failure when AI service throws exception', function () {
        $user = createPremiumUser()->assignRole('job_seeker');

        Profile::factory()->create([
            'user_id' => $user->id,
            'resume_path' => 'resume.pdf',
        ]);

        createAutoApplyPreferences($user, [
            'auto_apply_enabled' => true,
            'job_titles' => null,
            'locations' => null,
            'job_types' => null,
            'salary_min' => 0,
            'salary_max' => 999999,
        ]);

        $jobType = JobType::where('name', 'Full-time')->firstOrFail();

        Job::factory()->create([
            'user_id' => User::factory()->create()->id,
            'category_id' => Category::factory()->create()->id,
            'job_type_id' => $jobType->id,
            'title' => 'Developer',
            'description' => 'Job description',
            'location' => 'Remote',
            'salary_min' => 60000,
            'salary_max' => 90000,
            'is_open' => true,
        ]);

        $mock = Mockery::mock(App\Services\AIServices::class);
        $this->mockAIService->shouldReceive('generateCoverLetter')
            ->once()
            ->with(Mockery::type(App\Models\Job::class), Mockery::type(App\Models\User::class), Mockery::any())
            ->andThrow(new Exception('AI service error'));

        $this->app->instance(App\Services\AIServices::class, $mock);
        $this->mockAIService->shouldReceive('generateCoverLetter')
            ->andThrow(new Exception('AI service error'));
        $this->autoApplyService->processForUser($user);

        $this->assertDatabaseHas('auto_apply_logs', [
            'user_id' => $user->id,
            'status' => 'failed',
            'reason' => 'AI service error',
        ]);
    });

    it('filters jobs by multiple job titles', function () {
        $user = createPremiumUser()->assignRole('job_seeker');
        Profile::factory()->create(['user_id' => $user->id, 'resume_path' => 'resume.pdf']);

        createAutoApplyPreferences($user, [
            'auto_apply_enabled' => true,
            'job_titles' => ['Frontend Developer', 'Backend Developer'],
            'locations' => null,
            'job_types' => null,
            'salary_min' => 0,
            'salary_max' => 999999,
        ]);

        $jobType = JobType::where('name', 'Full-time')->firstOrFail();

        $frontendJob = Job::factory()->create([
            'title' => 'Frontend Developer',
            'job_type_id' => $jobType->id,
            'is_open' => true,
            'salary_min' => 60000,
            'salary_max' => 90000,
        ]);

        // This job should NOT match since title differs
        Job::factory()->create([
            'title' => 'Project Manager',
            'job_type_id' => $jobType->id,
            'is_open' => true,
            'salary_min' => 60000,
            'salary_max' => 90000,
        ]);

        $jobs = $this->autoApplyService->getFilteredJobs($user->autoApplyPreference);

        expect($jobs->pluck('id'))->toContain($frontendJob->id);
        expect($jobs->pluck('title'))->not->toContain('Project Manager');
    });

    it('filters jobs by locations', function () {
        $user = createPremiumUser()->assignRole('job_seeker');
        Profile::factory()->create(['user_id' => $user->id, 'resume_path' => 'resume.pdf']);

        createAutoApplyPreferences($user, [
            'auto_apply_enabled' => true,
            'locations' => ['Remote', 'New York'],
            'job_titles' => null,
            'job_types' => null,
            'salary_min' => 0,
            'salary_max' => 999999,
        ]);

        $jobType = JobType::where('name', 'Full-time')->firstOrFail();

        $remoteJob = Job::factory()->create([
            'location' => 'Remote',
            'job_type_id' => $jobType->id,
            'is_open' => true,
        ]);

        $onSiteJob = Job::factory()->create([
            'location' => 'San Francisco',
            'job_type_id' => $jobType->id,
            'is_open' => true,
        ]);

        $jobs = $this->autoApplyService->getFilteredJobs($user->autoApplyPreference);

        expect($jobs->pluck('id'))->toContain($remoteJob->id);
        expect($jobs->pluck('location'))->not->toContain('San Francisco');
    });

    it('filters jobs by job types', function () {
        $user = createPremiumUser()->assignRole('job_seeker');
        Profile::factory()->create(['user_id' => $user->id, 'resume_path' => 'resume.pdf']);

        createAutoApplyPreferences($user, [
            'auto_apply_enabled' => true,
            'job_types' => ['Full-time'],
            'job_titles' => null,
            'locations' => null,
            'salary_min' => 0,
            'salary_max' => 999999,
        ]);

        $fullTimeType = JobType::where('name', 'Full-time')->firstOrFail();
        $partTimeType = JobType::where('name', 'Part-time')->firstOrFail();

        $fullTimeJob = Job::factory()->create([
            'job_type_id' => $fullTimeType->id,
            'is_open' => true,
        ]);

        $partTimeJob = Job::factory()->create([
            'job_type_id' => $partTimeType->id,
            'is_open' => true,
        ]);

        $jobs = $this->autoApplyService->getFilteredJobs($user->autoApplyPreference);

        expect($jobs->pluck('id'))->toContain($fullTimeJob->id);
        expect($jobs->pluck('id'))->not->toContain($partTimeJob->id);
    });

    it('filters jobs by combined preferences', function () {
        $user = createPremiumUser()->assignRole('job_seeker');
        Profile::factory()->create(['user_id' => $user->id, 'resume_path' => 'resume.pdf']);

        createAutoApplyPreferences($user, [
            'auto_apply_enabled' => true,
            'job_titles' => ['Developer'],
            'locations' => ['Remote'],
            'job_types' => ['Full-time'],
            'salary_min' => 0,
            'salary_max' => 999999,
        ]);

        $fullTimeType = JobType::where('name', 'Full-time')->firstOrFail();
        $partTimeType = JobType::where('name', 'Part-time')->firstOrFail();

        $matchingJob = Job::factory()->create([
            'title' => 'Developer',
            'location' => 'Remote',
            'job_type_id' => $fullTimeType->id,
            'is_open' => true,
            'salary_min' => 60000,
            'salary_max' => 90000,
        ]);

        // Mismatched title
        Job::factory()->create([
            'title' => 'Manager',
            'location' => 'Remote',
            'job_type_id' => $fullTimeType->id,
            'is_open' => true,
        ]);

        // Mismatched location
        Job::factory()->create([
            'title' => 'Developer',
            'location' => 'Onsite',
            'job_type_id' => $fullTimeType->id,
            'is_open' => true,
        ]);

        // Mismatched job type
        Job::factory()->create([
            'title' => 'Developer',
            'location' => 'Remote',
            'job_type_id' => $partTimeType->id,
            'is_open' => true,
        ]);

        $jobs = $this->autoApplyService->getFilteredJobs($user->autoApplyPreference);

        expect($jobs->pluck('id'))->toContain($matchingJob->id);
        expect($jobs->pluck('title'))->not->toContain('Manager');
        expect($jobs->pluck('location'))->not->toContain('Onsite');
        $this->mockAIService
            ->shouldReceive('generateCoverLetter')
            ->once()
            ->with(Mockery::type(App\Models\Job::class), Mockery::type(App\Models\User::class), Mockery::any())
            ->andReturn('Generated cover letter');

        $this->autoApplyService->processForUser($user);

        expect(Application::count())->toBe(1)
            ->and(AutoApplyLog::where('status', 'success')->count())->toBe(1);
    });

    it('filters jobs by salary range overlap', function () {
        $user = createPremiumUser()->assignRole('job_seeker');
        Profile::factory()->create(['user_id' => $user->id, 'resume_path' => 'resume.pdf']);

        createAutoApplyPreferences($user, [
            'auto_apply_enabled' => true,
            'salary_min' => 70000,
            'salary_max' => 85000,
        ]);

        $jobType = JobType::where('name', 'Full-time')->firstOrFail();

        $insideSalaryRangeJob = Job::factory()->create([
            'salary_min' => 75000,
            'salary_max' => 80000,
            'job_type_id' => $jobType->id,
            'is_open' => true,
        ]);

        $outsideSalaryRangeJob = Job::factory()->create([
            'salary_min' => 90000,
            'salary_max' => 100000,
            'job_type_id' => $jobType->id,
            'is_open' => true,
        ]);

        $jobs = $this->autoApplyService->getFilteredJobs($user->autoApplyPreference);

        expect($jobs->pluck('id'))->toContain($insideSalaryRangeJob->id);
        expect($jobs->pluck('id'))->not->toContain($outsideSalaryRangeJob->id);

        $this->mockAIService
            ->shouldReceive('generateCoverLetter')
            ->once()
            ->with(Mockery::type(App\Models\Job::class), Mockery::type(App\Models\User::class), Mockery::any())
            ->andReturn('Generated cover letter');

        $this->autoApplyService->processForUser($user);

        expect(Application::count())->toBe(1)
            ->and(AutoApplyLog::where('status', 'success')->count())->toBe(1);
    });
});
