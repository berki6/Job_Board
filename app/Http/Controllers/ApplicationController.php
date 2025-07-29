<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Routing\Controller;

class ApplicationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }
}
