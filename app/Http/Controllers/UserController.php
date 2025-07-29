<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Profile;
use App\Models\Skill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controller;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('premium')->only(['premiumFeature']);
    }

    // Show user profile
    public function profile(Request $request)
    {
        $user = $request->user();
        $profile = $user->profile()->with('user.skills')->firstOrFail();
        return view('profile.show', compact('profile'));
    }

    // Show profile edit form
    public function editProfile(Request $request)
    {
        $user = $request->user();
        $profile = $user->profile()->firstOrCreate(['user_id' => $user->id]);
        return view('profile.edit', compact('profile'));
    }
}
