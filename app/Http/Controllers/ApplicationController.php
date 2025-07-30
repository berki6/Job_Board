<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Job;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ApplicationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // Show application form
    public function create(Job $job)
    {
        $this->authorize('apply_jobs', Auth::user());
        if (! $job->is_open) {
            return redirect()->route('jobs.show', $job->slug)->with('error', 'Job is closed');
        }

        return view('applications.create', compact('job'));
    }

    // Submit application
    public function store(Request $request, Job $job)
    {
        $user = Auth::user();
        $this->authorize('apply_jobs', $user);

        if (! $job->is_open) {
            return redirect()->route('jobs.show', $job->slug)->with('error', 'Job is closed');
        }

        $validator = Validator::make($request->all(), [
            'resume' => 'required|file|mimes:pdf|max:5120',
            'cover_letter' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $application = $job->applications()->create([
            'user_id' => $user->id,
            'resume_path' => $request->file('resume')->store('resumes', 'public'),
            'cover_letter' => $request->cover_letter,
            'status' => 'pending',
        ]);

        \App\Jobs\NotifyEmployerJob::dispatch($job->user, $application);

        return redirect()->route('dashboard.job-seeker')->with('success', 'Application submitted');
    }

    // Update application status
    public function updateStatus(Request $request, Application $application)
    {
        $this->authorize('update', $application);

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,reviewed,rejected',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $application->update(['status' => $request->status]);
        \App\Jobs\NotifyJobSeekerJob::dispatch($application->user, $application);

        return redirect()->route('dashboard.employer')->with('success', 'Application status updated');
    }
}
