<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

// Helper function to create a user with premium subscription
function createPremiumUser($attributes = [])
{
    $user = \App\Models\User::factory()->create($attributes);
    $user->createAsStripeCustomer();
    $user->newSubscription('premium', 'price_1Rp1zVFIcfi7ZhWBuRQYYZn0')->create('pm_card_visa');
    return $user;
}

// Helper function to create a basic user
function createUser($attributes = [])
{
    return \App\Models\User::factory()->create($attributes);
}

// Helper function to create a user with profile
function createUserWithProfile($userAttributes = [], $profileAttributes = [])
{
    $user = createUser($userAttributes);
    $user->profile()->create(array_merge([
        'bio' => 'Test bio',
        'phone' => '+1234567890',
        'resume_path' => 'resumes/test.pdf',
        'skills' => ['PHP', 'Laravel', 'JavaScript']
    ], $profileAttributes));
    return $user;
}

// Helper function to create a job
function createJob($attributes = [])
{
    $company = createUser(['name' => 'Test Company']);
    return \App\Models\Job::factory()->create(array_merge([
        'company_id' => $company->id,
        'title' => 'Software Developer',
        'description' => 'Great job opportunity',
        'location' => 'Remote',
        'salary' => 75000,
        'status' => 'open'
    ], $attributes));
}
// Helper function to create an application
function createApplication($user, $job, $attributes = [])
{
    return \App\Models\Application::factory()->create(array_merge([
        'user_id' => $user->id,
        'job_id' => $job->id,
        'cover_letter' => 'This is a cover letter',
        'status' => 'pending'
    ], $attributes));
}

function createAutoApplyPreferences($user, $attributes = [])
{
    return App\Models\AutoApplyPreference::factory()->create(array_merge([
        'user_id' => $user->id,
        'auto_apply_enabled' => false,
        'job_titles' => null,
        'locations' => null,
        'job_types' => null, // Optional; remove if not needed
        'salary_min' => null,
        'salary_max' => null,
        'cover_letter_template' => null,
    ], array_map(function ($value, $key) {
        return in_array($key, ['job_titles', 'locations', 'job_types']) && is_array($value) ? json_encode($value) : $value;
    }, $attributes, array_keys($attributes))));
}