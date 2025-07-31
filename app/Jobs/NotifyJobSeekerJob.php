<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;
use App\Notifications\JobSeekerNotification;

class NotifyJobSeekerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $jobSeeker;
    protected $application;

    /**
     * Create a new job instance.
     */
    public function __construct(User $jobSeeker, Application $application)
    {
        $this->jobSeeker = $jobSeeker;
        $this->application = $application;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $message = "Your application for {$this->application->job->title} has been {$this->application->status}";
        Notification::send($this->jobSeeker, new JobSeekerNotification($message));
    }

    public function getJobSeeker()
    {
        return $this->jobSeeker;
    }

    public function getApplication()
    {
        return $this->application;
    }
}
