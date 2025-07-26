<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AutoApplyPreference;

class AutoApplyController extends Controller
{
    /**
     * Display the auto-apply settings page.
     */
    public function index(Request $request)
    {
        $preferences = $request->user()->autoApplyPreference;
        return view('auto-apply', compact('preferences'));
    }

    /**
     * Update the user's auto-apply preferences.
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'job_titles' => 'nullable|string',
            'locations' => 'nullable|string',
            'salary_min' => 'nullable|integer',
            'salary_max' => 'nullable|integer',
            'cover_letter_template' => 'nullable|string',
        ]);

        $preferences = $user->autoApplyPreference ?? new AutoApplyPreference(['user_id' => $user->id]);

        $preferences->fill([
            'job_titles' => $request->job_titles ? json_decode($request->job_titles, true) : null,
            'locations' => $request->locations ? json_decode($request->locations, true) : null,
            'salary_min' => $request->salary_min,
            'salary_max' => $request->salary_max,
            'cover_letter_template' => $request->cover_letter_template,
        ]);

        $preferences->save();

        return back()->with('success', 'Preferences updated successfully.');
    }

    /**
     * Toggle auto-apply on or off.
     */
    public function toggle(Request $request)
    {
        $preferences = $request->user()->autoApplyPreference;

        if ($preferences) {
            $preferences->auto_apply_enabled = !$preferences->auto_apply_enabled;
            $preferences->save();
        }

        return back()->with('success', 'Auto-Apply status updated.');
    }
}
