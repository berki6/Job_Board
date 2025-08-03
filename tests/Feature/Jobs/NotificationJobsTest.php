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
    uses(RefreshDatabase::class);
    beforeEach(function () {
        // Seed roles
        $this->artisan('db:seed', ['--class' => 'JobPermissionSeeder']);
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

        // Create an employer and job
        $employer = User::factory()->create()->assignRole('employer');
        $job = Job::factory()->create([
            'user_id' => $employer->id,
            'status' => 'published',
            'job_type_id' => JobType::where('name', 'Full-time')->first()->id,
            'category_id' => Category::where('name', 'Engineering')->first()->id,
        ]);
        Log::info('Employer and job created.', ['employer' => $employer->id, 'job' => $job->id]);
        // Act: Dispatch the NotifyEmployer job
        NotifyEmployerJob::dispatch($employer, $job, 'Test job notification');

        Queue::assertPushed(NotifyEmployerJob::class);

        // Manually run the job to save the notification
        $jobInstance = new NotifyEmployerJob($employer, $job, 'Test job notification');
        $jobInstance->handle();
        Log::info('NotifyEmployerJob handled.', ['employer' => $employer->id, 'job' => $job->id]);
        // Assert: Notification was stored in the database
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $employer->id,
            'notifiable_type' => User::class,
            'type' => EmployerNotification::class,
            'data' => json_encode(['data' => ['message' => 'Test job notification']]),
            'read_at' => null,
        ]);
        Log::info('Notification stored in database.', [
            'notifiable_id' => $employer->id,
            'notifiable_type' => User::class,
            'type' => EmployerNotification::class,
            'data' => json_encode(['data' => ['message' => 'Test job notification']]),
        ]);

        // Assert: Notification was created with the correct attributes
        $notification = \Illuminate\Notifications\DatabaseNotification::first();
        $this->assertEquals($employer->id, $notification->notifiable_id);
        $this->assertEquals(User::class, $notification->notifiable_type);
        $this->assertEquals(EmployerNotification::class, $notification->type);
        $this->assertEquals(['data' => ['message' => 'Test job notification']], $notification->data);
        $this->assertNull($notification->read_at);
        Log::info('Test completed successfully.');
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
        Log::info('NotifyJobSeekerJob dispatched.');

        Queue::assertPushed(NotifyJobSeekerJob::class, function ($dispatchedJob) use ($jobSeeker, $application) {
            return $dispatchedJob->getJobSeeker()->id === $jobSeeker->id &&
                $dispatchedJob->getApplication()->id === $application->id;
        });

        // Manually handle the job to trigger the notification
        $jobInstance = new NotifyJobSeekerJob($jobSeeker, $application);
        $jobInstance->handle();

        Log::info('NotifyJobSeekerJob handled.', ['jobSeeker' => $jobSeeker->id, 'application' => $application->id]);

        Notification::assertSentTo($jobSeeker, JobSeekerNotification::class, function ($notification) use ($application) {
            return $notification->message === "Your application for {$application->job->title} has been {$application->status}";
        });

        Log::info('Notification sent to job seeker.', [
            'jobSeeker' => $jobSeeker->id,
            'application' => $application->id,
            'message' => "Your application for {$application->job->title} has been {$application->status}"
        ]);

        // Notification::assertSentTo($jobSeeker, JobSeekerNotification::class, function ($notification, $channels) use ($application) {
        //     return $notification->toDatabase($notification->toDatabase($channels))->message === "Your application for {$application->job->title} has been {$application->status}";
        // });                              
    });

    it('dispatches NotifyJobSeeker job and stores notification', function () {
        // Arrange: Set up Redis queue and fake notifications
        Queue::fake();

        // Create a job seeker and application
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
        Log::info('NotifyJobSeekerJob handled.', ['jobSeeker' => $jobSeeker->id, 'application' => $application->id]);
        // Assert: Notification was stored in the database
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $jobSeeker->id,
            'notifiable_type' => User::class,
            'type' => JobSeekerNotification::class,
            'data' => json_encode(['data' => ['message' => "Your application for {$application->job->title} has been {$application->status}"]]),
            'read_at' => null,
        ]);

        Log::info('Notification stored in database.', [
            'notifiable_id' => $jobSeeker->id,
            'notifiable_type' => User::class,
            'type' => JobSeekerNotification::class,
            'data' => json_encode(['data' => ['message' => "Your application for {$application->job->title} has been {$application->status}"]]),
        ]);

        // Assert: Notification was created with the correct attributes
        $notification = \Illuminate\Notifications\DatabaseNotification::first();
        $this->assertEquals($jobSeeker->id, $notification->notifiable_id);
        $this->assertEquals(User::class, $notification->notifiable_type);
        $this->assertEquals(JobSeekerNotification::class, $notification->type);
        $this->assertEquals(['data' => ['message' => "Your application for {$application->job->title} has been {$application->status}"]], $notification->data);
        $this->assertNull($notification->read_at);
        Log::info('Test completed successfully.');
    });
});