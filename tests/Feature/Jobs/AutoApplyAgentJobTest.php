<?php

use App\Jobs\AutoApplyAgentJob;
use App\Models\User;
use App\Models\Job;
use App\Models\Profile;
use App\Models\AutoApplyPreference;
use App\Models\Application;
use App\Models\AutoApplyLog;
use App\Services\AutoApplyService;
use App\Services\AIServices;

describe('AutoApplyAgentJob', function () {
    it('processes premium users with auto apply enabled', function () {
        // Create premium user with profile and preferences
        $user = createPremiumUser();
        Profile::factory()->create([
            'user_id' => $user->id,
            'resume_path' => 'resumes/test.pdf'
        ]);
        createAutoApplyPreferences($user, [
            'auto_apply_enabled' => true,
            'job_titles' => ['Developer'],
            'locations' => ['Remote']
        ]);

        // Create matching job
        $job = Job::factory()->create([
            'title' => 'Developer',
            'location' => 'Remote',
            'status' => 'open'
        ]);

        // Mock AI service
        $this->mock(AIServices::class, function ($mock) {
            $mock->shouldReceive('generateCoverLetter')
                ->once()
                ->andReturn('Generated cover letter');
        });

        // Execute the job
        $job = new AutoApplyAgentJob();
        $job->handle(app(AutoApplyService::class));

        // Assert application was created
        expect(Application::count())->toBe(1)
            ->and(AutoApplyLog::where('status', 'success')->count())->toBe(1);
    });

    it('skips non-premium users', function () {
        // Create regular user
        $user = User::factory()->create();
        Profile::factory()->create(['user_id' => $user->id]);
        createAutoApplyPreferences($user, ['auto_apply_enabled' => true]);

        Job::factory()->create(['status' => 'open']);

        // Execute the job
        $job = new AutoApplyAgentJob();
        $job->handle(app(AutoApplyService::class));

        // Assert no applications were created
        expect(Application::count())->toBe(0)
            ->and(AutoApplyLog::count())->toBe(0);
    });

    it('skips users without auto apply preferences', function () {
        $user = createPremiumUser();
        Profile::factory()->create(['user_id' => $user->id]);
        // No preferences created

        Job::factory()->create(['status' => 'open']);

        $job = new AutoApplyAgentJob();
        $job->handle(app(AutoApplyService::class));

        expect(Application::count())->toBe(0);
    });

    it('skips users with disabled auto apply', function () {
        $user = createPremiumUser();
        Profile::factory()->create(['user_id' => $user->id]);
        createAutoApplyPreferences($user, ['auto_apply_enabled' => false]);

        Job::factory()->create(['status' => 'open']);

        $job = new AutoApplyAgentJob();
        $job->handle(app(AutoApplyService::class));

        expect(Application::count())->toBe(0);
    });

    it('processes multiple premium users', function () {
        // Create two premium users
        $user1 = createPremiumUser();
        Profile::factory()->create(['user_id' => $user1->id, 'resume_path' => 'resume1.pdf']);
        createAutoApplyPreferences($user1, ['auto_apply_enabled' => true]);

        $user2 = createPremiumUser();
        Profile::factory()->create(['user_id' => $user2->id, 'resume_path' => 'resume2.pdf']);
        createAutoApplyPreferences($user2, ['auto_apply_enabled' => true]);

        // Create job
        $job = Job::factory()->create(['status' => 'open']);

        // Mock AI service for both users
        $this->mock(AIServices::class, function ($mock) {
            $mock->shouldReceive('generateCoverLetter')
                ->twice()
                ->andReturn('Generated cover letter');
        });

        $autoApplyJob = new AutoApplyAgentJob();
        $autoApplyJob->handle(app(AutoApplyService::class));

        // Assert both users applied
        expect(Application::count())->toBe(2)
            ->and(AutoApplyLog::where('status', 'success')->count())->toBe(2);
    });

    it('handles users without profiles gracefully', function () {
        $user = createPremiumUser();
        // No profile created
        createAutoApplyPreferences($user, ['auto_apply_enabled' => true]);

        Job::factory()->create(['status' => 'open']);

        $job = new AutoApplyAgentJob();
        $job->handle(app(AutoApplyService::class));

        // Should create failure log
        expect(Application::count())->toBe(0)
            ->and(AutoApplyLog::where('status', 'failed')->count())->toBe(1);
            
        $log = AutoApplyLog::first();
        expect($log->reason)->toBe('User profile or resume not found');
    });

    it('loads users with relationships', function () {
        $user = createPremiumUser();
        Profile::factory()->create(['user_id' => $user->id]);
        createAutoApplyPreferences($user, ['auto_apply_enabled' => true]);

        // Ensure relationships are loaded to avoid N+1 queries
        $this->spy(AutoApplyService::class, function ($spy) {
            $spy->shouldReceive('processForUser')
                ->once()
                ->with(Mockery::on(function ($user) {
                    return $user->relationLoaded('autoApplyPreference') &&
                           $user->relationLoaded('profile');
                }));
        });

        $job = new AutoApplyAgentJob();
        $job->handle(app(AutoApplyService::class));
    });

    it('implements ShouldQueue interface', function () {
        $job = new AutoApplyAgentJob();
        
        expect($job)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
    });
});
