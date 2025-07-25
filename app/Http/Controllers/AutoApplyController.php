<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AutoApplyPreference;

class AutoApplyController extends Controller
{
    public function index()
    {
        $preferences = auth()->user()->autoApplyPreference;
        return view('auto-apply', compact('preferences'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'job_titles' => 'nullable|string',
            'locations' => 'nullable|string',
            'salary_min' => 'nullable|integer',
            'salary_max' => 'nullable|integer',
            'cover_letter_template' => 'nullable|string'
        ]);

        $preferences = auth()->user()->autoApplyPreference ?? new AutoApplyPreference(['user_id' => auth()->id()]);
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

    public function toggle()
    {
        $preferences = auth()->user()->autoApplyPreference;
        if ($preferences) {
            $preferences->auto_apply_enabled = !$preferences->auto_apply_enabled;
            $preferences->save();
        }
        return back()->with('success', 'Auto-Apply status updated.');
    }
}
