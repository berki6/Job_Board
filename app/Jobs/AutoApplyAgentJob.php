<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\AutoApplyService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class AutoApplyAgentJob implements ShouldQueue
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
        $users = User::whereHas('subscriptions', fn ($q) => $q->where('type', 'premium'))
            ->with('autoApplyPreference', 'profile')
            ->get();

        Log::info('Users fetched for auto apply job', [
            'count' => $users->count(),
            'ids' => $users->pluck('id')->toArray(),
            'subscriptions' => $users->map(fn ($u) => $u->subscriptions->toArray())->toArray(),
        ]);

        foreach ($users as $user) {
            Log::info('Processing user', ['user_id' => $user->id]);
            // Process each user with the AutoApplyService
            Log::info('Processing auto-apply for user', ['user_id' => $user->id]);
            if (! $user->subscribed('premium')) {
                Log::info('User not premium, skipping', ['user_id' => $user->id]);

                continue;
            }
            Log::info('User passes initial checks', ['user_id' => $user->id]);
            // Call the service to process the user
            Log::info('Calling AutoApplyService for user', ['user_id' => $user->id]);
            Log::info('AutoApplyAgentJob processing for user', ['user_id' => $user->id]);
            Log::info('AutoApplyAgentJob processing auto-apply for user', ['user_id' => $user->id]);
            $service->processForUser($user);
        }
    }
}
