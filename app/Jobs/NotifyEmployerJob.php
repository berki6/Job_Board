<?php

namespace App\Jobs;

use App\Models\Application;
use App\Models\Job;
use App\Models\User;
use App\Notifications\EmployerNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

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
        Log::info('NotifyEmployerJob is being handled for employer: '.$this->employer->id);
        if ($this->model instanceof Application) {
            $message = $this->message ?? "New application received for {$this->model->job->title}";
        } elseif ($this->model instanceof Job) {
            $message = $this->message ?? "Job status updated: {$this->model->title}";
        } else {
            Log::error('NotifyEmployerJob received unexpected model type: '.get_class($this->model));

            return; // Or throw an exception, depending on how you want to handle this
        }

        try {
            Notification::send($this->employer, new EmployerNotification($message));
            Log::info('Notification sent successfully to employer: '.$this->employer->id);
        } catch (\Throwable $e) {
            Log::error('Failed to send notification to employer: '.$this->employer->id.'. Error: '.$e->getMessage());
            // Consider releasing the job back onto the queue (with a delay) for retry
            // $this->release(60); // Release back onto the queue after 60 seconds
            // OR you can use the failed() method to handle failures.
        }
    }

    public function failed(\Throwable $exception): void
    {
        // This method is called when the job fails.
        Log::critical('NotifyEmployerJob failed: '.$exception->getMessage().' for employer: '.$this->employer->id);
        // You can implement custom logic here, such as sending an alert to the developers.
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
