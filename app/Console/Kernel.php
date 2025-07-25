<?php

use App\Jobs\AutoApplyAgentJob;
use Illuminate\Console\Scheduling\Schedule;

class Kernel {
protected function schedule(Schedule $schedule)
{
    $schedule->job(new AutoApplyAgentJob)->everyFifteenMinutes();
}
'premium' => \App\Http\Middleware\PremiumMiddleware::class;
}