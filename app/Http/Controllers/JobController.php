<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Models\Category;
use App\Models\JobType;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class JobController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth')->except(['index', 'show']);
    }

    /**
     * Display a listing of the jobs.
     * List published, open jobs (public)
     *
     * @return \Illuminate\View\View
     */
    // List published, open jobs (public)
    public function index(Request $request)
    {
        $jobs = Cache::remember('jobs_page_' . $request->page, 3600, function () {
            return Job::where('status', 'published')
                ->where('is_open', true)
                ->with(['user.profile', 'jobType', 'category'])
                ->paginate(20);
        });
        return view('jobs.index', compact('jobs'));
    }

    /**
     * Display a single job.
     * Show job details (public)
     *
     * @return \Illuminate\View\View
     */
    public function show($slug)
    {
        $job = Job::where('slug', $slug)
            ->where('status', 'published')
            ->with(['user.profile', 'jobType', 'category'])
            ->firstOrFail();
        return view('jobs.show', compact('job'));
    }

    /**
     * Show the form for creating a new job.
     * Show job creation form (auth)
     * 
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $this->authorize('post_jobs', Auth::user());
        $categories = Category::all();
        $jobTypes = JobType::all();
        return view('jobs.create', compact('categories', 'jobTypes'));
    }

    // Create job
    public function store(Request $request)
    {
        $user = Auth::user();
        $this->authorize('post_jobs', $user);

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'location' => 'nullable|string|max:255',
            'salary_min' => 'nullable|numeric|min:0',
            'salary_max' => 'nullable|numeric|min:0|gte:salary_min',
            'job_type_id' => 'required|exists:job_types,id',
            'category_id' => 'required|exists:categories,id',
            'remote' => 'boolean',
            'application_method' => 'in:form,external',
            'external_link' => 'nullable|url|required_if:application_method,external'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $job = $user->jobs()->create([
            'title' => $request->title,
            'description' => $request->description,
            'location' => $request->location,
            'salary_min' => $request->salary_min,
            'salary_max' => $request->salary_max,
            'job_type_id' => $request->job_type_id,
            'category_id' => $request->category_id,
            'remote' => $request->remote ?? false,
            'application_method' => $request->application_method ?? 'form',
            'external_link' => $request->external_link,
            'status' => 'draft',
            'is_open' => true,
        ]);

        return redirect()->route('jobs.index')->with('success', 'Job created, pending approval');
    }
}
