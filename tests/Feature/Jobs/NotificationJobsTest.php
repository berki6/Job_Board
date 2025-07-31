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
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

describe('NotificationJob', function () {

    beforeEach(function () {
        // // Seed roles
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'employer']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'job_seeker']);
        // Seed job types and categories
        JobType::firstOrCreate(['name' => 'Full-time']);
        Category::firstOrCreate(['name' => 'Engineering'], ['slug' => Str::slug('Engineering')]);
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

        Queue::assertPushed(NotifyEmployerJob::class, function ($dispatchedJob) use ($employer, $job) {
            return $dispatchedJob->getEmployer()->id === $employer->id &&
                   $dispatchedJob->getModel()->id === $job->id &&
                   $dispatchedJob->getMessage() === 'Test job notification';
        });

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

        // Remove notification fake to allow actual DB insert
        Notification::swap(new \Illuminate\Notifications\ChannelManager(app()));

        // Manually run the job to save the notification
        $jobInstance = new NotifyEmployerJob($employer, $job, 'Test job notification');
        $jobInstance->handle();

        // Assert: Notification was stored in the database
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $employer->id,
            'notifiable_type' => User::class,
            'data' => json_encode(['message' => 'Test job notification']),
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

        Notification::assertSentTo($jobSeeker, JobSeekerNotification::class, function ($notification, $channels) use ($application) {
            return $notification->message === "Your application for {$application->job->title} has been {$application->status}";
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

        Notification::swap(new \Illuminate\Notifications\ChannelManager(app()));

        $jobInstance = new NotifyJobSeekerJob($jobSeeker, $application);
        $jobInstance->handle();

        // Assert: Notification was stored in the database
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $jobSeeker->id,
            'notifiable_type' => User::class,
            'data' => json_encode(['message' => "Your application for {$application->job->title} has been {$application->status}"]),
        ]);
    });
});