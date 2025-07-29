<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Job;
use App\Models\JobType;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    // Show search form and results
    public function search(Request $request)
    {
        $query = $request->input('q');
        $categories = Category::all();
        $jobTypes = JobType::all();

        // If there is a search query, use Scout's search
        if (!empty($query)) {
            $jobs = Job::search($query)
                ->where('status', 'published')
                ->where('is_open', true);
        } else {
            // No search query: fallback to regular query builder
            $jobs = Job::query()
                ->where('status', 'published')
                ->where('is_open', true);
        }

        if ($request->has('location')) {
            $jobs = $jobs->where('location', $request->location);
        }

        if ($request->has('job_type_id')) {
            $jobs = $jobs->where('job_type_id', $request->job_type_id);
        }

        if ($request->has('category_id')) {
            $jobs = $jobs->where('category_id', $request->category_id);
        }

        if ($request->has('remote')) {
            $jobs = $jobs->where('remote', filter_var($request->remote, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('salary_min')) {
            $jobs = $jobs->where('salary_min', '>=', $request->salary_min);
        }

        if ($request->has('salary_max')) {
            $jobs = $jobs->where('salary_max', '<=', $request->salary_max);
        }

        $sort = $request->input('sort', 'relevance');
        if ($sort === 'newest') {
            $jobs = $jobs->orderBy('created_at', 'desc');
        } elseif ($sort === 'salary') {
            $jobs = $jobs->orderBy('salary_max', 'desc');
        }

        $jobs = $jobs->paginate(20);
        return view('search.index', compact('jobs', 'categories', 'jobTypes', 'query'));
    }
}
