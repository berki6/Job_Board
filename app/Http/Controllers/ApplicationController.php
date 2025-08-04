<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Job;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ApplicationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // List applications for the authenticated user
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user->hasPermissionTo('application_view', 'web')) {
            // deny access
            abort(403, 'Unauthorized');
        }
        $applications = Application::where('user_id', $user->id)->with('job')->get();

        return view('applications.index', compact('applications'));
    }

    // Show a specific application
    public function show(Request $request, Application $application)
    {
        $user = $request->user();
        if (! $user->hasPermissionTo('application_view', 'web')) {
            // deny access
            abort(403, 'Unauthorized');
        }
        if ($application->user_id !== $user->id) {
            return redirect()->route('applications.index')->with('error', 'Unauthorized access to application');
        }

        return view('applications.show', compact('application'));
    }

    // Show application form
    public function create(Job $job)
    {
        $user = request()->user();
        if (! $user->hasPermissionTo('application_create', 'web')) {
            // deny access
            abort(403, 'Unauthorized');
        }
        if (! $job->is_open) {
            return redirect()->route('jobs.show', $job->slug)->with('error', 'Job is closed');
        }

        return view('applications.create', compact('job'));
    }

    // Submit application
    public function store(Request $request, Job $job)
    {
        $user = $request->user();
        if (! $user->hasPermissionTo('apply_jobs', 'web')) {
            // deny access
            abort(403, 'Unauthorized');
        }

        if (! $job->is_open) {
            return redirect()->route('jobs.show', $job->slug)->with('error', 'Job is closed');
        }

        $validator = Validator::make($request->all(), [
            'resume' => ['required', 'file', 'mimes:pdf', 'max:2048'],
            'cover_letter' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $application = $job->applications()->create([
            'job_id' => $job->id,
            'user_id' => $user->id,
            'resume_path' => $request->file('resume')->store('resumes', 'public'),
            'cover_letter' => $request->cover_letter,
            'status' => 'pending',
        ]);
        $resumePath = $request->file('resume')->store('resumes', 'public');
        $request->session()->put('resume_path', $resumePath);

        \App\Jobs\NotifyEmployerJob::dispatch($job->user, $application);

        return redirect()->route('job-seeker.dashboard')->with('success', 'Application submitted');
    }

    // Show application status update form
    public function edit(Application $application)
    {
        $user = request()->user();
        if (! $user->hasPermissionTo('application_update', 'web')) {
            // deny access
            abort(403, 'Unauthorized');
        }
        if ($application->user_id !== $user->id) {
            return redirect()->route('applications.index')->with('error', 'Unauthorized access to application');
        }

        return view('applications.edit', compact('application'));
    }

    // Update application
    public function update(Request $request, Application $application)
    {
        $user = $request->user();
        if (! $user->hasPermissionTo('application_update', 'web')) {
            // deny access
            abort(403, 'Unauthorized');
        }
        if ($application->user_id !== $user->id) {
            return redirect()->route('applications.index')->with('error', 'Unauthorized access to application');
        }
        $validator = Validator::make($request->all(), [
            'resume' => ['required', 'file', 'mimes:pdf', 'max:2048'],
            'cover_letter' => ['nullable', 'string'],
        ]);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $application->update([
            'resume_path' => $request->file('resume') ? $request->file('resume')->store('resumes', 'public') : $application->resume_path,
            'cover_letter' => $request->cover_letter,
        ]);

        return redirect()->route('applications.show', $application)->with('success', 'Application updated');
    }

    // Update application status
    public function updateStatus(Request $request, Application $application)
    {
        $user = $request->user();
        if (! $user->hasPermissionTo('application_update_status', 'web')) {
            // deny access
            abort(403, 'Unauthorized');
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,approved,rejected',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $application->update(['status' => $request->status]);
        \App\Jobs\NotifyJobSeekerJob::dispatch($application->user, $application);

        return redirect()->route('employer.dashboard')->with('success', 'Application status updated');
    }

    public function destroy(Request $request, Job $job)
    {
        $user = $request->user();
        if (! $user->hasPermissionTo('application_destroy', 'web')) {
            // deny access
            abort(403, 'Unauthorized');
        }
        Log::info('ApplicationController destroy method called', ['job_id' => $job->id, 'user_id' => $user->id]);
        $application = $job->applications()->where('user_id', $user->id)->first();
        // if (!$application) {
        //     return redirect()->route('jobs.show', $job->slug)->with('error', 'Application not found');
        // }
        $application->delete();
        Log::info('Application deleted', ['application_id' => $application->id, 'user_id' => $user->id]);

        return redirect()->route('job-seeker.dashboard')->with('success', 'Application deleted');
    }
}
