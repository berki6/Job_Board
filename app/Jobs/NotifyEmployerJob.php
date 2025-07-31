<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Application;
use App\Models\Job;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;
use App\Notifications\EmployerNotification;

class NotifyEmployerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $employer;
    protected $model;
    protected $message;

    /**
     * Create a new job instance.
     */
    public function __construct(User $employer, $model, $message = null)
    {
        $this->employer = $employer;
        $this->model = $model;
        $this->message = $message;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->model instanceof Application) {
            $message = $this->message ?? "New application received for {$this->model->job->title}";
        } elseif ($this->model instanceof Job) {
            $message = $this->message ?? "Job status updated: {$this->model->title}";
        }

        Notification::send($this->employer, new EmployerNotification($message));
    }

    public function getEmployer()
    {
        return $this->employer;
    }

    public function getModel()
    {
        return $this->model;
    }

    public function getMessage()
    {
        return $this->message;
    }
    
}
