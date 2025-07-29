<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Models\Category;
use App\Models\JobType;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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
}
