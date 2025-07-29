<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Routing\Controller;

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
        if (!$job->is_open) {
            return redirect()->route('jobs.show', $job->slug)->with('error', 'Job is closed');
        }
        return view('applications.create', compact('job'));
    }
}
