<?php

use App\Models\Application;
use App\Models\AutoApplyPreference;
use App\Models\Job;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

describe('User Model', function () {
    it('can create a user', function () {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        expect($user->name)->toBe('John Doe')
            ->and($user->email)->toBe('john@example.com');
    });

    it('has a profile relationship', function () {
        $user = User::factory()->create();
        $profile = Profile::factory()->create(['user_id' => $user->id]);

        expect($user->profile)->toBeInstanceOf(Profile::class)
            ->and($user->profile->id)->toBe($profile->id);
    });

    it('has an auto apply preference relationship', function () {
        $user = User::factory()->create();
        $preference = $user->autoApplyPreference()->create([
            'auto_apply_enabled' => true,
            'job_titles' => ['Developer'],
            'locations' => ['Remote'],
        ]);

        expect($user->autoApplyPreference)->toBeInstanceOf(AutoApplyPreference::class)
            ->and($user->autoApplyPreference->auto_apply_enabled)->toBeTrue();
    });

    it('has applications relationship', function () {
        $user = User::factory()->create();
        $job = Job::factory()->create();
        $application = Application::factory()->create([
            'user_id' => $user->id,
            'job_id' => $job->id,
        ]);

        expect($user->applications)->toHaveCount(1)
            ->and($user->applications->first())->toBeInstanceOf(Application::class);
    });

    it('has jobs relationship for companies', function () {
        $company = User::factory()->create(['name' => 'Test Company']);
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
