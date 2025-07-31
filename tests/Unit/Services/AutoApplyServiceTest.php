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
use Illuminate\Support\Facades\DB;

describe('AutoApplyService', function () {
    beforeEach(function () {
        // $this->artisan('migrate:fresh');
        // DB::statement('PRAGMA foreign_keys = ON;');
        // Seed job types
        JobType::firstOrCreate(['name' => 'Full-time']);
        JobType::firstOrCreate(['name' => 'Part-time']);
        JobType::firstOrCreate(['name' => 'Contract']);

        // Verify seeding
        if (!JobType::where('name', 'Full-time')->exists()) {
            throw new Exception('Failed to seed Full-time JobType');
        }

        $this->mockAIService = Mockery::mock(AIServices::class);
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
        $user = createPremiumUser();

        $this->autoApplyService->processForUser($user);

        expect(AutoApplyLog::count())->toBe(0)
            ->and(Application::count())->toBe(0);
    });

    it('skips users with disabled auto apply', function () {
        $user = createPremiumUser();
        createAutoApplyPreferences($user, ['auto_apply_enabled' => false]);

        $this->autoApplyService->processForUser($user);

        expect(AutoApplyLog::count())->toBe(0)
            ->and(Application::count())->toBe(0);
    });

    it('processes premium users with enabled auto apply', function () {
        $user = createPremiumUser();
        Profile::factory()->create([
            'user_id' => $user->id,
            'resume_path' => 'resumes/test.pdf',
        ]);

        createAutoApplyPreferences($user, [
            'auto_apply_enabled' => true,
            'job_titles' => ['Developer'],
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
            'salary' => 75000.00,
            'status' => 'open',
        ]);

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
        $user = createPremiumUser();

        Profile::factory()->create(['user_id' => $user->id, 'resume_path' => 'resume.pdf']);

        createAutoApplyPreferences($user, [
            'auto_apply_enabled' => true,
            'job_titles' => ['Frontend Developer'],
            'job_types' => null,
            'locations' => null,
        ]);

        $jobType = JobType::where('name', 'Full-time')->firstOrFail();
        Job::factory()->create([
            'user_id' => User::factory()->create()->id,
            'category_id' => Category::factory()->create()->id,
            'job_type_id' => $jobType->id,
            'title' => 'Backend Developer',
            'description' => 'Job description',
            'location' => 'Remote',
            'salary' => 75000.00,
            'status' => 'open',
        ]);
        $matchingJob = Job::factory()->create([
            'user_id' => User::factory()->create()->id,
            'category_id' => Category::factory()->create()->id,
            'job_type_id' => $jobType->id,
            'title' => 'Frontend Developer',
            'description' => 'Job description',
            'location' => 'Remote',
            'salary' => 75000.00,
            'status' => 'open',
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
        $user = createPremiumUser();

        Profile::factory()->create(['user_id' => $user->id, 'resume_path' => 'resume.pdf']);

        createAutoApplyPreferences($user, [
            'auto_apply_enabled' => true,
            'job_titles' => null,
            'job_types' => null,
            'locations' => null,
            'salary_min' => 60000,
            'salary_max' => 90000,
        ]);

        $jobType = JobType::where('name', 'Full-time')->firstOrFail();
        Job::factory()->create([
            'user_id' => User::factory()->create()->id,
            'category_id' => Category::factory()->create()->id,
            'job_type_id' => $jobType->id,
            'title' => 'Developer',
            'description' => 'Job description',
            'location' => 'Remote',
            'salary' => 40000.00,
            'status' => 'open',
        ]);
        Job::factory()->create([
            'user_id' => User::factory()->create()->id,
            'category_id' => Category::factory()->create()->id,
            'job_type_id' => $jobType->id,
            'title' => 'Developer',
            'description' => 'Job description',
            'location' => 'Remote',
            'salary' => 100000.00,
            'status' => 'open',
        ]);
        $matchingJob = Job::factory()->create([
            'user_id' => User::factory()->create()->id,
            'category_id' => Category::factory()->create()->id,
            'job_type_id' => $jobType->id,
            'title' => 'Developer',
            'description' => 'Job description',
            'location' => 'Remote',
            'salary' => 75000.00,
            'status' => 'open',
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

    it('filters jobs by location preferences', function () {
        $user = createPremiumUser();

        Profile::factory()->create(['user_id' => $user->id, 'resume_path' => 'resume.pdf']);

        createAutoApplyPreferences($user, [
            'auto_apply_enabled' => true,
            'job_titles' => null,
            'job_types' => null,
            'locations' => ['Remote'],
        ]);

        $jobType = JobType::where('name', 'Full-time')->firstOrFail();
        Job::factory()->create([
            'user_id' => User::factory()->create()->id,
            'category_id' => Category::factory()->create()->id,
            'job_type_id' => $jobType->id,
            'title' => 'Developer',
            'description' => 'Job description',
            'location' => 'New York',
            'salary' => 75000.00,
            'status' => 'open',
        ]);
        $matchingJob = Job::factory()->create([
            'user_id' => User::factory()->create()->id,
            'category_id' => Category::factory()->create()->id,
            'job_type_id' => $jobType->id,
            'title' => 'Developer',
            'description' => 'Job description',
            'location' => 'Remote',
            'salary' => 75000.00,
            'status' => 'open',
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
        $user = User::factory()->create();
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
            'salary' => 75000.00,
            'status' => 'open',
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
        $user = createPremiumUser();
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
            'salary' => 75000.00,
            'status' => 'open',
        ]);

        $this->autoApplyService->processForUser($user);

        expect(AutoApplyLog::where('status', 'failed')->count())->toBe(1);

        $log = AutoApplyLog::first();
        expect($log->reason)->toBe('User profile or resume not found');
    });

    it('logs failure when AI service throws exception', function () {
        $user = createPremiumUser();
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
            'salary' => 75000.00,
            'status' => 'open',
        ]);

        $this->mockAIService
            ->shouldReceive('generateCoverLetter')
            ->once()
            ->with(Mockery::type(App\Models\Job::class), Mockery::type(App\Models\User::class), Mockery::any())
            ->andThrow(new Exception('AI service error'));

        $this->autoApplyService->processForUser($user);

        expect(AutoApplyLog::where('status', 'failed')->count())->toBe(1);

        $log = AutoApplyLog::first();
        expect($log->reason)->toBe('AI service error');
    });
});
