<?php

namespace App\Http\Controllers;

use App\Models\JobAlert;
use App\Models\Category;
use App\Models\JobType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

class JobAlertController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // Show job alert creation form
    public function create()
    {
        $this->authorize('create-job-alert', Auth::user());
        $categories = Category::all();
        $jobTypes = JobType::all();
        return view('job-alerts.create', compact('categories', 'jobTypes'));
    }

    // Create job alert
    public function store(Request $request)
    {
        // $user = Auth::user();
        $user = $request->user();
        $this->authorize('create-job-alert', $user);

        $validator = Validator::make($request->all(), [
            'keywords' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'job_type_id' => 'nullable|exists:job_types,id',
            'frequency' => 'required|in:daily,weekly'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $user->jobAlerts()->create($request->only([
            'keywords',
            'location',
            'category_id',
            'job_type_id',
            'frequency'
        ]));

        return redirect()->route('dashboard.job-seeker')->with('success', 'Job alert created');
    }

    // Delete job alert
    public function destroy(JobAlert $jobAlert)
    {
        $this->authorize('delete-job-alert', $jobAlert);
        $jobAlert->delete();
        return redirect()->route('dashboard.job-seeker')->with('success', 'Job alert deleted');
    }
}
