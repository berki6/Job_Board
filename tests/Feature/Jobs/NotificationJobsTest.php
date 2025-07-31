<?php

use App\Models\User;
use App\Models\Job;
use App\Models\Application;
use App\Jobs\NotifyEmployerJob;
use App\Jobs\NotifyJobSeekerJob;
use App\Models\Category;
use App\Models\JobType;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

describe('NotificationJob', function () {

    beforeEach(function () {
        // // Seed roles
        \Spatie\Permission\Models\Role::create(['name' => 'employer']);
        \Spatie\Permission\Models\Role::create(['name' => 'job_seeker']);
        // Seed job types and categories
        JobType::firstOrCreate(['name' => 'Full-time']);
        Category::firstOrCreate(['name' => 'Engineering'], ['slug' => Str::slug('Engineering')]);
    });

    it('dispatches NotifyEmployer job and stores notification', function () {
        // Arrange: Set up Redis queue and fake notifications
        Queue::fake();
        Notification::fake();

        // Create an employer and job
        $employer = User::factory()->create()->assignRole('employer');
        $job = Job::factory()->create([
            'user_id' => $employer->id,
            'status' => 'published',
            'job_type_id' => JobType::where('name', 'Full-time')->first()->id,
            'category_id' => Category::where('name', 'Engineering')->first()->id,
        ]);

        // Act: Dispatch the NotifyEmployer job
        NotifyEmployerJob::dispatch($employer, $job, 'Test job notification');

        // Assert: Job was pushed to Redis queue
        Queue::assertPushed(NotifyEmployerJob::class, function ($dispatchedJob) use ($employer, $job) {
            return $dispatchedJob->getEmployer()->id === $employer->id &&
                $dispatchedJob->getModel()->id === $job->id &&
                $dispatchedJob->getMessage() === 'Test job notification';
        });

        // Simulate queue processing
        Queue::assertPushed(NotifyEmployerJob::class, function ($job) {
            $job->handle();
            return true;
        });

        // Assert: Notification was stored in the database
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $employer->id,
            'notifiable_type' => User::class,
            'data' => json_encode(['message' => 'Test job notification']),
        ]);
    });

    it('dispatches NotifyJobSeeker job and stores notification', function () {
        // Arrange: Set up Redis queue and fake notifications
        Queue::fake();
        Notification::fake();

        // Create a job seeker, employer, job, and application
        $jobSeeker = User::factory()->create()->assignRole('job_seeker');
        $employer = User::factory()->create()->assignRole('employer');
        $job = Job::factory()->create([
            'user_id' => $employer->id,
            'status' => 'published',
            'job_type_id' => JobType::where('name', 'Full-time')->first()->id,
            'category_id' => Category::where('name', 'Engineering')->first()->id,
        ]);
        $application = Application::factory()->create([
            'user_id' => $jobSeeker->id,
            'job_id' => $job->id,
            'status' => 'approved'
        ]);

        // Act: Dispatch the NotifyJobSeeker job
        NotifyJobSeekerJob::dispatch($jobSeeker, $application);

        // Assert: Job was pushed to Redis queue
        Queue::assertPushed(NotifyJobSeekerJob::class, function ($dispatchedJob) use ($jobSeeker, $application) {
            return $dispatchedJob->getJobSeeker()->id === $jobSeeker->id && $dispatchedJob->getApplication()->id === $application->id;
        });

        // Simulate queue processing
        Queue::assertPushed(NotifyJobSeekerJob::class, function ($job) {
            $job->handle();
            return true;
        });

        // Assert: Notification was stored in the database
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $jobSeeker->id,
            'notifiable_type' => User::class,
            'data' => json_encode(['message' => "Your application for {$application->job->title} has been {$application->status}"]),
        ]);
    });
});