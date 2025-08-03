<?php

use App\Models\Application;
use App\Models\AutoApplyPreference;
use App\Models\Category;
use App\Models\Job;
use App\Models\JobAlert;
use App\Models\JobType;
use App\Models\Profile;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
beforeEach(function () {
    // Set up the database and necessary roles/permissions
    // $this->artisan('migrate');
    $this->artisan('db:seed', ['--class' => 'JobPermissionSeeder']);
    // // Create a user with job seeker role
    // $user = User::factory()->create()->assignRole('job_seeker');
    // $this->actingAs($user); 
});

describe('User Model', function () {
    it('can be created with valid data', function () {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'FyLbI@example.com',
            'password' => 'password',
        ]);

        expect($user->name)->toBe('John Doe')
            ->and($user->email)->toBe('FyLbI@example.com');

    });

    it('validates email uniqueness', function () {
        User::factory()->create(['email' => 'FyLbI@example.com']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        User::factory()->create(['email' => 'FyLbI@example.com']);
    });

    it('validates password length', function () {
        $user = User::factory()->make(['password' => 'short']);

        expect($user->password)->toHaveLength(60); // Laravel hashes passwords to 60 characters
    });

    it('has a job_seeker role', function () {
        $user = User::factory()->create()->assignRole('job_seeker');

        expect($user->hasRole('job_seeker'))->toBeTrue();
    });

    it('has a company role', function () {
        $user = User::factory()->create(['name' => 'Test Company'])->assignRole('employer');

        expect($user->hasRole('employer'))->toBeTrue();
    });

    it('has a job seeker profile', function () {
        $user = User::factory()->create();
        $profile = Profile::factory()->create(['user_id' => $user->id]);

        expect($user->profile)->toBeInstanceOf(Profile::class)
            ->and($user->profile->id)->toBe($profile->id);
    });

    it('can create a profile', function () {
        $user = User::factory()->create();
        $skills = ['PHP', 'JavaScript', 'Laravel'];
        $profile = Profile::factory()->create([
            'user_id' => $user->id,
            'bio' => 'This is a test bio.',
            'phone' => '123-456-7890',
            'website' => 'https://example.com',
            'logo_path' => null, // Assuming logo_path is nullable
            'resume_path' => null, // Assuming resume_path is nullable
            'skills' => $skills,
        ])->fresh(); 

        expect($profile->user_id)->toBe($user->id)
            ->and($profile->bio)->toBe('This is a test bio.')
            ->and($profile->phone)->toBe('123-456-7890')
            ->and($profile->website)->toBe('https://example.com')
            ->and($profile->logo_path)->toBeNull()
            ->and($profile->resume_path)->toBeNull()
            ->and($profile->skills)->toBe(['PHP', 'JavaScript', 'Laravel']);        
    });

    it('can create a profile without optional fields', function () {
        $user = User::factory()->create();
        $profile = Profile::factory()->create([
            'user_id' => $user->id,
            'bio' => 'This is a test bio.',
            'phone' => '123-456-7890',
            'logo_path' => null, 
            'skills' => ['PHP', 'JavaScript', 'Laravel'],
        ])->fresh(); // Use fresh to get the latest state of the model after creation

        expect($profile->user_id)->toBe($user->id)
            ->and($profile->bio)->toBe('This is a test bio.')
            ->and($profile->phone)->toBe('123-456-7890')
            ->and($profile->logo_path)->toBeNull()
            ->and($profile->skills)->toBe(['PHP', 'JavaScript', 'Laravel']);
    });

    it('can update profile', function () {
        $user = User::factory()->create();
        $profile = Profile::factory()->create(['user_id' => $user->id]);

        $profile->update(['bio' => 'Updated bio']);

        expect($user->profile->bio)->toBe('Updated bio');
    });

    it('can delete profile', function () {
        $user = User::factory()->create();
        $profile = Profile::factory()->create(['user_id' => $user->id]);

        $profile->delete();

        expect($user->profile)->toBeNull();
    });

    it('can update auto apply preference', function () {
        $user = User::factory()->create();
        $preference = $user->autoApplyPreference()->create([
            'auto_apply_enabled' => true,
            'job_titles' => ['Developer'],
            'locations' => ['Remote'],
        ]);
        $preference->update(['auto_apply_enabled' => false]);

        expect($user->autoApplyPreference->auto_apply_enabled)->toBeFalse();
    });

    it('can delete auto apply preference', function () {
        $user = User::factory()->create();
        $preference = $user->autoApplyPreference()->create([
            'auto_apply_enabled' => true,
            'job_titles' => ['Developer'],
            'locations' => ['Remote'],
        ]);

        $preference->delete();

        expect($user->autoApplyPreference)->toBeNull();
    });

    it('can create job applications', function () {
        $user = User::factory()->create()->assignRole('job_seeker');
        $job = Job::factory()->create();

        $application = $user->applications()->create([
            'job_id' => $job->id,
            'cover_letter' => 'This is a cover letter.',
            'resume_path' => 'path/to/resume.pdf', // Provide a value for the resume_path column
        ]);

        expect($application->user_id)->toBe($user->id)
            ->and($application->job_id)->toBe($job->id)
            ->and($application->cover_letter)->toBe('This is a cover letter.')
            ->and($application->resume_path)->toBe('path/to/resume.pdf');
    });

    it('can save jobs', function () {
        $user = User::factory()->create()->assignRole('job_seeker');
        $job = Job::factory()->create(['user_id' => $user->id]);
        $user->savedJobs()->attach($job->id);

        expect($user->savedJobs)->toHaveCount(1)
            ->and($user->savedJobs->first()->id)->toBe($job->id)
            ->and($user->savedJobs->first()->pivot->user_id)->toBe($user->id)
            ->and($user->savedJobs->first()->pivot->job_id)->toBe($job->id);
    });

    it('can create job alerts', function () {
        $user = User::factory()->create()->assignRole('job_seeker');
        $category = Category::factory()->create();
        $jobType = JobType::factory()->create();
        $alert = $user->jobAlerts()->create([
            'keywords' => ['developer'],
            'location' => ['remote'],
            'category_id' => $category->id,
            'job_type_id' => $jobType->id,
            'frequency' => 'daily',
        ]);
        // dd($alert);
        expect($alert->user_id)->toBe($user->id)
            ->and($alert->keywords)->toBe(['developer'])
            ->and($alert->location)->toBe(['remote'])
            ->and($alert->category_id)->toBe($category->id)
            ->and($alert->job_type_id)->toBe($jobType->id)
            ->and($alert->frequency)->toBe('daily');
    });

    it('can make payments', function () {
        $user = User::factory()->create()->assignRole('job_seeker');
        $job = Job::factory()->create(['user_id' => $user->id]);

        // Create a payment for the job
        $payment = $user->payments()->create([
            'job_id' => $job->id,
            'user_id' => $user->id,
            'amount' => fake()->randomFloat(2, 10, 1000), // Use a random float for the amount
            'status' => 'completed',
            'stripe_id' => fake()->uuid(), // Use a fake UUID for the stripe_id
        ])->fresh(); // Use fresh to get the latest state of the model after creation

        expect($payment->user_id)->toBe($user->id)
            ->and($payment->job_id)->toBe($job->id)
            ->and($payment->amount)->toBeGreaterThan(0)
            ->and($payment->status)->toBe('completed')
            ->and($payment->stripe_id)->toBeString();
    });

    it('can check if user is banned', function () {
        $user = User::factory()->create(['is_banned' => true]);

        expect($user->is_banned)->toBeTrue();
    });

    it('can check if user is not banned', function () {
        $user = User::factory()->create(['is_banned' => false]);

        expect($user->is_banned)->toBeFalse();
    });

    // it('can check if user is premium', function () {
    //     $user = User::factory()->create(['is_premium' => true]);

    //     expect($user->isPremium())->toBeTrue();
    // });

    // it('can check if user is not premium', function () {
    //     $user = User::factory()->create(['is_premium' => false]);

    //     expect($user->isPremium())->toBeFalse();
    // });

    it('can check if user is job seeker', function () {
        $user = User::factory()->create();
        $user->assignRole('job_seeker');

        expect($user->hasRole('job_seeker'))->toBeTrue();
    });

    it('can check if user is not job seeker', function () {
        $user = User::factory()->create();

        expect($user->hasRole('job_seeker'))->toBeFalse();
    });

    it('can check if user is employer', function () {
        $user = User::factory()->create(['name' => 'Test Company']);
        $user->assignRole('employer');

        expect($user->hasRole('employer'))->toBeTrue();
    });

    it('can check if user is not employer', function () {
        $user = User::factory()->create();

        expect($user->hasRole('employer'))->toBeFalse();
    });

    it('has skills relationship', function () {
        $user = User::factory()->create()->assignRole('job_seeker');
        $skill = Skill::factory()->create();
        $user->skills()->attach($skill->id);

        expect($user->skills)->toHaveCount(1)
            ->and($user->skills->first()->id)->toBe($skill->id);
    });

    it('can add skills', function () {
        $user = User::factory()->create()->assignRole('job_seeker');
        $skill = Skill::factory()->create();
        $user->skills()->attach($skill->id);

        expect($user->skills)->toHaveCount(1)
            ->and($user->skills->first()->id)->toBe($skill->id)
            ->and($user->skills->first()->pivot->user_id)->toBe($user->id)
            ->and($user->skills->first()->pivot->skill_id)->toBe($skill->id);
    });

    it('has payments relationship', function () {
        $user = User::factory()->create();
        $payment = $user->payments()->create([
            'job_id' => Job::factory()->create(['user_id' => $user->id])->id,
            'amount' => 100.00,
            'status' => 'completed',
            'stripe_id' => fake()->uuid(),
        ]);

        expect($user->payments)->toHaveCount(1)
            ->and($user->payments->first()->id)->toBe($payment->id);
    });

    it('has job alerts relationship', function () {
        $user = User::factory()->create()->assignRole('job_seeker');
        $alert = JobAlert::factory()->create(['user_id' => $user->id]);

        expect($user->jobAlerts)->toHaveCount(1)
            ->and($user->jobAlerts->first()->id)->toBe($alert->id);
    });

    it('has saved jobs relationship', function () {
        $user = User::factory()->create()->assignRole('job_seeker');
        $job = Job::factory()->create(['user_id' => $user->id]);
        $user->savedJobs()->attach($job->id);

        expect($user->savedJobs)->toHaveCount(1)
            ->and($user->savedJobs->first()->id)->toBe($job->id)
            ->and($user->savedJobs->first()->pivot->user_id)->toBe($user->id)
            ->and($user->savedJobs->first()->pivot->job_id)->toBe($job->id);
    });

    it('has a hashed password', function () {
        $user = User::factory()->create(['password' => 'password']);

        expect($user->password)->not->toBe('password')
            ->and(Hash::check('password', $user->getAuthPassword()))->toBeTrue();
    });

    it('has a valid email verification date', function () {
        $user = User::factory()->create(['email_verified_at' => now()]);

        expect($user->email_verified_at)->toBeInstanceOf(\Carbon\Carbon::class);
    });

    it('has a trial end date', function () {
        $user = User::factory()->create(['trial_ends_at' => now()->addDays(30)]);

        expect($user->trial_ends_at)->toBeInstanceOf(\Carbon\Carbon::class)
            ->and($user->trial_ends_at->isFuture())->toBeTrue();
    });

    it('has a profile relationship', function () {
        $user = User::factory()->create();
        $profile = Profile::factory()->create(['user_id' => $user->id]);

        expect($user->profile)->toBeInstanceOf(Profile::class)
            ->and($user->profile->id)->toBe($profile->id);
    });

    it('has an auto apply preference relationship', function () {
        $user = User::factory()->create()->assignRole('job_seeker');
        $preference = $user->autoApplyPreference()->create([
            'auto_apply_enabled' => true,
            'job_titles' => ['Developer'],
            'locations' => ['Remote'],
        ]);

        expect($user->autoApplyPreference)->toBeInstanceOf(AutoApplyPreference::class)
            ->and($user->autoApplyPreference->auto_apply_enabled)->toBeTrue()
            ->and($user->autoApplyPreference->job_titles)->toBe(['Developer'])
            ->and($user->autoApplyPreference->locations)->toBe(['Remote'])
            ->and($user->autoApplyPreference->id)->toBe($preference->id);
    });

    it('has applications relationship', function () {
        $user = User::factory()->create()->assignRole('job_seeker');
        $job = Job::factory()->create(['user_id' => $user->id]);
        $application = Application::factory()->create([
            'user_id' => $user->id,
            'job_id' => $job->id,
        ]);

        expect($user->applications)->toHaveCount(1)
            ->and($user->applications->first())->toBeInstanceOf(Application::class)
            ->and($user->applications->first()->job_id)->toBe($job->id)
            ->and($user->applications->first()->user_id)->toBe($user->id)
            ->and($user->applications->first()->cover_letter)->toBe($application->cover_letter)
            ->and($user->applications->first()->resume_path)->toBe($application->resume_path);
    });

    it('has jobs relationship for companies', function () {
        $company = User::factory()->create(['name' => 'Test Company'])->assignRole('employer');
        $job = Job::factory()->create(['user_id' => $company->id]);

        expect($company->jobs)->toHaveCount(1)
            ->and($company->jobs->first())->toBeInstanceOf(Job::class);
    });

    it('hashes password on creation', function () {
        $user = User::factory()->create(['password' => 'password']);

        expect($user->password)->not->toBe('password')
            ->and(Hash::check('password', $user->getAuthPassword()))->toBeTrue();
    });
});
