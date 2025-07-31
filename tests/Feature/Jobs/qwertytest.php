<?php

namespace Tests\Feature\Jobs;

use App\Models\User;
use App\Models\Job;
use App\Models\Application;
use App\Models\Category;
use App\Models\JobType;
use App\Jobs\NotifyEmployerJob;
use App\Jobs\NotifyJobSeekerJob;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Replace \App\Notifications\* with your actual notification classes your jobs dispatch
use App\Notifications\EmployerNotification;
use App\Notifications\JobSeekerNotification;

class qwertytest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'employer']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'job_seeker']);
        // Use firstOrCreate to avoid duplicate entry errors
        JobType::firstOrCreate(['name' => 'Full-time']);
        Category::firstOrCreate(
            ['name' => 'Engineering']
        );
    }

    // ----------- NotifyEmployerJob Tests -------------

    /** @test */
    public function it_dispatches_notify_employer_job_and_sends_notification()
    {
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
    }

    /** @test */
    public function it_dispatches_notify_employer_job_and_stores_notification_in_database()
    {
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

        Queue::assertPushed(NotifyEmployerJob::class);

        // Remove notification fake to allow actual DB insert
        Notification::swap(new \Illuminate\Notifications\ChannelManager(app()));

        // Manually run the job to save the notification
        $jobInstance = new NotifyEmployerJob($employer, $job, 'Test job notification');
        $jobInstance->handle();

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $employer->id,
            'notifiable_type' => User::class,
            'data' => json_encode(['message' => 'Test job notification']),
        ]);
    }

    // ----------- NotifyJobSeekerJob Tests -------------

    /** @test */
    public function it_dispatches_notify_job_seeker_job_and_sends_notification()
    {
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
            'status' => 'pending',  // Use a valid status as per DB constraint
        ]);

        NotifyJobSeekerJob::dispatch($jobSeeker, $application);

        Queue::assertPushed(NotifyJobSeekerJob::class, function ($job) use ($jobSeeker, $application) {
            return $job->getJobSeeker()->id === $jobSeeker->id &&
                $job->getApplication()->id === $application->id;
        });

        Notification::assertSentTo($jobSeeker, JobSeekerNotification::class, function ($notification, $channels) use ($application) {
            return $notification->message === "Your application for {$application->job->title} has been {$application->status}";
        });
    }

    /** @test */
    public function it_dispatches_notify_job_seeker_job_and_stores_notification_in_database()
    {
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
            'status' => 'pending',
        ]);

        NotifyJobSeekerJob::dispatch($jobSeeker, $application);

        Queue::assertPushed(NotifyJobSeekerJob::class);

        Notification::swap(new \Illuminate\Notifications\ChannelManager(app()));

        $jobInstance = new NotifyJobSeekerJob($jobSeeker, $application);
        $jobInstance->handle();

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $jobSeeker->id,
            'notifiable_type' => User::class,
            'data' => json_encode(['message' => "Your application for {$application->job->title} has been {$application->status}"]),
        ]);
    }
}
