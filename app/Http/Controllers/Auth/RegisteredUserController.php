<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'confirmed', Rules\Password::defaults()],
                'user_role' => ['required', 'in:job_seeker,employer'],
            ]);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            if (! $user) {
                Log::error('User creation failed', ['request' => $request->all()]);

                return back()->withErrors(['email' => 'User creation failed.']);
            }

            $user->assignRole($request->user_role);

            event(new Registered($user));

            Auth::login($user);

            if (! Auth::check()) {
                Log::error('Login failed after user creation', ['user_id' => $user->id]);

                return back()->withErrors(['email' => 'Login failed after registration.']);
            }

            // Redirect based on user role
            if ($request->user_role === 'job_seeker') {
                return redirect()->route('job-seeker.dashboard'); // Redirect to job seeker dashboard
            } elseif ($request->user_role === 'employer') {
                return redirect()->route('employer.dashboard'); // Redirect to employer dashboard
            }
        } catch (\Exception $e) {
            Log::error('Registration error', ['error' => $e->getMessage(), 'request' => $request->all()]);

            return back()->withErrors(['email' => 'An error occurred during registration.']);
        }
    }
}
