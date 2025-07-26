<?php

use App\Services\AutoApplyService;
use App\Services\AIServices;
use App\Models\User;
use App\Models\Job;
use App\Models\Profile;
use App\Models\AutoApplyPreference;
use App\Models\Application;
use App\Models\AutoApplyLog;
use Illuminate\Support\Facades\DB;

describe('AutoApplyService', function () {
    beforeEach(function () {
        DB::statement('PRAGMA foreign_keys = ON;');

        // Initialize Mockery
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
        $user = User::factory()->create();
        $this->mock('alias:Illuminate\Contracts\Auth\Access\Gate')
            ->shouldReceive('check')
            ->with('subscribed', ['premium'])
            ->andReturn(true);

        Profile::factory()->create([
            'user_id' => $user->id,
            'resume_path' => 'resumes/test.pdf'
        ]);

        createAutoApplyPreferences($user, [
            'auto_apply_enabled' => true,
            'job_titles' => ['Developer'],
            'locations' => ['Remote']
        ]);

        $job = Job::factory()->create([
            'title' => 'Developer',
            'location' => 'Remote',
            'status' => 'open'
        ]);

        $this->mockAIService
            ->shouldReceive('generateCoverLetter')
            ->once()
            ->with($job, $user, null)
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
        $user = User::factory()->create();
        $this->mock('alias:Illuminate\Contracts\Auth\Access\Gate')
            ->shouldReceive('check')
            ->with('subscribed', ['premium'])
            ->andReturn(true);

        Profile::factory()->create(['user_id' => $user->id, 'resume_path' => 'resume.pdf']);

        createAutoApplyPreferences($user, [
            'auto_apply_enabled' => true,
            'job_titles' => ['Frontend Developer'],
            'locations' => []
        ]);

        Job::factory()->create(['title' => 'Backend Developer', 'status' => 'open']);
        $matchingJob = Job::factory()->create(['title' => 'Frontend Developer', 'status' => 'open']);

        $this->mockAIService
            ->shouldReceive('generateCoverLetter')
            ->once()
            ->with($matchingJob, $user, null) // Adjust based on actual cover_letter_template
            ->andReturn('Cover letter');

        $this->autoApplyService->processForUser($user);

        expect(Application::count())->toBe(1)
            ->and(Application::first()->job_id)->toBe($matchingJob->id);
    });

    it('filters jobs by salary range', function () {
        $user = User::factory()->create();
        $this->mock('alias:Illuminate\Contracts\Auth\Access\Gate')
            ->shouldReceive('check')
            ->with('subscribed', ['premium'])
            ->andReturn(true);

        Profile::factory()->create(['user_id' => $user->id, 'resume_path' => 'resume.pdf']);

        createAutoApplyPreferences($user, [
            'auto_apply_enabled' => true,
            'job_titles' => [],
            'locations' => [],
            'salary_min' => 60000,
            'salary_max' => 90000
        ]);

        Job::factory()->create(['salary' => 40000, 'status' => 'open']);
        Job::factory()->create(['salary' => 100000, 'status' => 'open']);
        $matchingJob = Job::factory()->create(['salary' => 75000, 'status' => 'open']);

        $this->mockAIService
            ->shouldReceive('generateCoverLetter')
            ->once()
            ->with($matchingJob, $user, null)
            ->andReturn('Cover letter');

        $this->autoApplyService->processForUser($user);

        expect(Application::count())->toBe(1)
            ->and(Application::first()->job_id)->toBe($matchingJob->id);
    });

    it('filters jobs by location preferences', function () {
        $user = User::factory()->create();
        $this->mock('alias:Illuminate\Contracts\Auth\Access\Gate')
            ->shouldReceive('check')
            ->with('subscribed', ['premium'])
            ->andReturn(true);

        Profile::factory()->create(['user_id' => $user->id, 'resume_path' => 'resume.pdf']);

        createAutoApplyPreferences($user, [
            'auto_apply_enabled' => true,
            'job_titles' => [],
            'locations' => ['Remote']
        ]);

        Job::factory()->create(['location' => 'New York', 'status' => 'open']);
        $matchingJob = Job::factory()->create(['location' => 'Remote', 'status' => 'open']);

        $this->mockAIService
            ->shouldReceive('generateCoverLetter')
            ->once()
            ->with($matchingJob, $user, null)
            ->andReturn('Cover letter');

        $this->autoApplyService->processForUser($user);

        expect(Application::count())->toBe(1)
            ->and(Application::first()->job_id)->toBe($matchingJob->id);
    });

    it('skips jobs already applied to', function () {
        $user = User::factory()->create();
        $this->mock('alias:Illuminate\Contracts\Auth\Access\Gate')
            ->shouldReceive('check')
            ->with('subscribed', ['premium'])
            ->andReturn(true);

        Profile::factory()->create(['user_id' => $user->id, 'resume_path' => 'resume.pdf']);
        createAutoApplyPreferences($user, ['auto_apply_enabled' => true]);

        $job = Job::factory()->create(['status' => 'open']);

        Application::factory()->create([
            'user_id' => $user->id,
            'job_id' => $job->id
        ]);

        $this->autoApplyService->processForUser($user);

        expect(Application::count())->toBe(1)
            ->and(AutoApplyLog::count())->toBe(0);
    });

    it('logs failure when user has no profile', function () {
        $user = createPremiumUser();
        createAutoApplyPreferences($user, ['auto_apply_enabled' => true]);
        $job = Job::factory()->create(['status' => 'open']);

        $this->autoApplyService->processForUser($user);

        expect(AutoApplyLog::where('status', 'failed')->count())->toBe(1);

        $log = AutoApplyLog::first();
        expect($log->reason)->toBe('User profile or resume not found');
    });

    it('logs failure when AI service throws exception', function () {
        $user = User::factory()->create();
        $this->mock('alias:Illuminate\Contracts\Auth\Access\Gate')
            ->shouldReceive('check')
            ->with('subscribed', ['premium'])
            ->andReturn(true);

        Profile::factory()->create(['user_id' => $user->id, 'resume_path' => 'resume.pdf']);
        createAutoApplyPreferences($user, ['auto_apply_enabled' => true]);
        $job = Job::factory()->create(['status' => 'open']);

        $this->mockAIService
            ->shouldReceive('generateCoverLetter')
            ->once()
            ->with($job, $user, null)
            ->andThrow(new Exception('AI service error'));

        $this->autoApplyService->processForUser($user);

        expect(AutoApplyLog::where('status', 'failed')->count())->toBe(1);

        $log = AutoApplyLog::first();
        expect($log->reason)->toBe('AI service error');
    });
});
