<?php

namespace App\Http\Controllers;

use App\Models\Skill;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\ImageManager as Image;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        // $this->middleware('premium')->only(['premiumFeature']);
        // (getback to it)
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

    // Update profile
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $this->authorize('update-profile', $user);

        $validator = Validator::make($request->all(), [
            'bio' => 'nullable|string|max:1000',
            'resume' => 'nullable|file|mimes:pdf|max:5120',
            'company_name' => 'nullable|string|max:255|required_if:role,employer',
            'website' => 'nullable|url|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $profile = $user->profile()->firstOrCreate(['user_id' => $user->id]);

        $data = $request->only(['bio', 'company_name', 'website']);

        if ($request->hasFile('resume') && $user->hasRole('job_seeker')) {
            if ($profile->resume_path) {
                Storage::delete($profile->resume_path);
            }
            $data['resume_path'] = $request->file('resume')->store('resumes', 'public');
        }

        if ($request->hasFile('logo') && $user->hasRole('employer')) {
            if ($profile->logo_path) {
                Storage::delete($profile->logo_path);
            }
            $upload = $request->file('logo');
            $image = Image::imagick()->read($upload)->cover(200, 200)->encode();
            $path = 'logos/'.uniqid().'.'.$request->file('logo')->extension();
            Storage::put($path, $image);
            $data['logo_path'] = $path;
        }

        $profile->update($data);

        return redirect()->route('profile.show')->with('success', 'Profile updated');
    }

    // Delete the user's account.
    public function destroyAccount(Request $request)
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);
        $user = $request->user();
        Auth::logout();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    // Show skills form
    public function editSkills(Request $request)
    {
        $user = $request->user();
        $this->authorize('update-skills', $user);
        $skills = Skill::all();
        $userSkills = $user->skills->pluck('name')->toArray();

        return view('profile.skills', compact('skills', 'userSkills'));
    }

    // Add skills
    public function addSkills(Request $request)
    {
        $user = $request->user();
        $this->authorize('update-skills', $user);

        $validator = Validator::make($request->all(), [
            'skills' => 'required|array',
            'skills.*' => 'string|exists:skills,name',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $user->skills()->syncWithoutDetaching($request->skills);

        return redirect()->route('profile.show')->with('success', 'Skills added');
    }

    // Remove skills
    public function removeSkills(Request $request)
    {
        $user = $request->user();
        $this->authorize('update-skills', $user);

        $validator = Validator::make($request->all(), [
            'skills' => 'required|array',
            'skills.*' => 'string|exists:skills,name',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $user->skills()->detach($request->skills);

        return redirect()->route('profile.show')->with('success', 'Skills removed');
    }
}
