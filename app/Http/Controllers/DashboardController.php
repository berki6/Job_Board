<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Models\Application;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // Job seeker dashboard
    public function jobSeeker(Request $request)
    {
        // $user = Auth::user();
        $user = $request->user();
        $this->authorize('view-job-seeker-dashboard', $user);

        $profile = $user->profile;
        $skills = $user->skills;
        $applications = $user->applications()->with('job')->get();
        $savedJobs = $user->savedJobs()->with('jobType', 'category')->get();
        $jobAlerts = $user->jobAlerts()->with('category', 'jobType')->get();

        return view('dashboard.job-seeker', compact('profile', 'skills', 'applications', 'savedJobs', 'jobAlerts'));
    }

    // Employer dashboard
    public function employer(Request $request)
    {
        // $user = Auth::user();
        $user = $request->user();
        $this->authorize('view-employer-dashboard', $user);

        $jobs = $user->jobs()->with(['jobType', 'category'])->get();
        $applications = Application::whereHas('job', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->with('user.profile')->get();
        $analytics = [
            'total_views' => $user->jobs()->sum('views'),
            'total_applications' => Application::whereHas('job', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })->count(),
        ];

        return view('dashboard.employer', compact('jobs', 'applications', 'analytics'));
    }

    // Admin dashboard
    public function admin(Request $request)
    {
        $this->authorize('view-admin-dashboard', $request->user());

        $stats = [
            'total_jobs' => Job::count(),
            'total_users' => User::count(),
            'total_applications' => Application::count(),
            'pending_jobs' => Job::where('status', 'draft')->count(),
        ];

        return view('dashboard.admin', compact('stats'));
    }
}
