<?php

use App\Models\User;
use App\Models\Job;
use App\Models\Application;
use App\Jobs\NotifyEmployerJob;
use App\Jobs\NotifyJobSeekerJob;
use App\Models\Category;
use App\Models\JobType;
use App\Notifications\EmployerNotification;
use App\Notifications\JobSeekerNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Notification;

// Don't use RefreshDatabase here since it's already used globally

describe('NotificationJob', function () {

    beforeEach(function () {
        // Seed roles
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'employer', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'job_seeker', 'guard_name' => 'web']);
        // Seed job types and categories
        JobType::updateOrCreate(['name' => 'Full-time'], ['name' => 'Full-time']);
        Category::updateOrCreate(['name' => 'Engineering'], ['name' => 'Engineering']);
    });

    it('dispatches notify employer job and sends notification', function () {
        Queue::fake();
        Notification::fake();

        $employer = User::factory()->create()->assignRole('employer');
        $job = Job::factory()->create([
            'user_id' => $employer->id,
            'status' => 'published',
            'job_type_id' => JobType::where('name', 'Full-time')->first()->id,
            'category_id' => Category::where('name', 'Engineering')->first()->id,
        ]);

        NotifyEmployerJob::dispatch($employer, $job, 'Test job notification');
        Log::info('NotifyEmployerJob dispatched.');

        Queue::assertPushed(NotifyEmployerJob::class);

        // Manually handle the job to save notification
        $jobInstance = new NotifyEmployerJob($employer, $job, 'Test job notification');
        $jobInstance->handle();

        Notification::assertSentTo($employer, EmployerNotification::class, function ($notification, $channels) {
            return $notification->message === 'Test job notification';
        });
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

        Queue::assertPushed(NotifyEmployerJob::class);

        // Manually run the job to save the notification
        $jobInstance = new NotifyEmployerJob($employer, $job, 'Test job notification');
        $jobInstance->handle();

        // Assert: Notification was stored in the database
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $employer->id,
            'notifiable_type' => User::class,
            'type' => EmployerNotification::class,
            'data' => json_encode(['data' => ['message' => 'Test job notification']]),
            'read_at' => null,
        ]);
    });

    it('dispatches NotifyJobSeeker job and sends notification', function () {
        Queue::fake();
        Notification::fake();

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

        NotifyJobSeekerJob::dispatch($jobSeeker, $application);

        Queue::assertPushed(NotifyJobSeekerJob::class, function ($dispatchedJob) use ($jobSeeker, $application) {
            return $dispatchedJob->getJobSeeker()->id === $jobSeeker->id &&
                   $dispatchedJob->getApplication()->id === $application->id;
        });

        // Manually handle the job to trigger the notification
        $jobInstance = new NotifyJobSeekerJob($jobSeeker, $application);
        $jobInstance->handle();

        Notification::assertSentTo($jobSeeker, JobSeekerNotification::class, function ($notification) use ($application) {
            return $notification->toDatabase($notification->notifiable)['data']['message'] === "Your application for {$application->job->title} has been {$application->status}";
        });
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

        Queue::assertPushed(NotifyJobSeekerJob::class);

        // Manually run the job to save the notification
        $jobInstance = new NotifyJobSeekerJob($jobSeeker, $application);
        $jobInstance->handle();

        // Assert: Notification was stored in the database
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $jobSeeker->id,
            'notifiable_type' => User::class,
            'type' => JobSeekerNotification::class,
            'data' => json_encode(['data' => ['message' => "Your application for {$application->job->title} has been {$application->status}"]]),
            'read_at' => null,
        ]);
    });
});