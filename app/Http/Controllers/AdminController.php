<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Models\JobRejection;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:admin');
    }

    // List pending jobs
    public function pendingJobs(Request $request)
    {
        $jobs = Job::where('status', 'draft')->with(['user.profile', 'jobType', 'category'])->paginate(20);

        return view('admin.jobs.pending', compact('jobs'));
    }

    // Approve job
    public function approveJob(Job $job)
    {
        $job->update(['status' => 'published']);
        Cache::forget('jobs_page_'.request()->page);
        \App\Jobs\NotifyEmployerJob::dispatch($job->user, $job, 'Job approved');

        return redirect()->route('admin.jobs.pending')->with('success', 'Job approved');
    }

    // Show job rejection form
    public function showRejectJob(Job $job)
    {
        return view('admin.jobs.reject', compact('job'));
    }

    // Reject job
    public function rejectJob(Request $request, Job $job)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $job->update(['status' => 'draft']);
        JobRejection::create([
            'job_id' => $job->id,
            'reason' => $request->reason,
        ]);

        \App\Jobs\NotifyEmployerJob::dispatch($job->user, $job, 'Job rejected: '.$request->reason);

        return redirect()->route('admin.jobs.pending')->with('success', 'Job rejected');
    }

    // List users
    public function users(Request $request)
    {
        $users = User::with('profile')->paginate(20);

        return view('admin.users.index', compact('users'));
    }

    // Ban user
    public function banUser(User $user)
    {
        $user->update(['is_banned' => true]);

        return redirect()->route('admin.users.index')->with('success', 'User banned');
    }
}
