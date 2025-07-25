<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\User;
use App\Models\Job;
use App\Services\AutoApplyService;

class AutoApplyAgentJob extends Job
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(AutoApplyService $service)
    {
        $users = User::whereHas('subscriptions', fn($q) => $q->where('name', 'premium'))
            ->with('autoApplyPreference', 'profile')
            ->get();

        foreach ($users as $user) {
            $service->processForUser($user);
        }
    }
}
