<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;

describe('Profile Page', function () {
    beforeEach(function () {
        // Set up the database and necessary roles/permissions
        // $this->artisan('migrate');
        $this->artisan('db:seed', ['--class' => 'JobPermissionSeeder']);
        // // Create a user with job seeker role
        // $user = User::factory()->create()->assignRole('job_seeker');
        // $this->actingAs($user);
    });
    test('profile page is displayed', function () {
        $user = User::factory()->create()->assignRole('employer');
        $user->profile()->firstOrCreate(['user_id' => $user->id]);
        $response = $this->actingAs($user)->get('/profile');

        $response->assertOk();
    });

    test('profile information can be updated', function () {
        $user = User::factory()->create()->assignRole('job_seeker');
        $user->profile()->firstOrCreate(['user_id' => $user->id]);
        $response = $this->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'bio' => 'This is a test bio.',
                'resume_path' => UploadedFile::fake()->create('resume.pdf', 1000, 'application/pdf'),
                'company_name' => 'Test Company',
                'website' => 'https://example.com',
                'logo_path' => UploadedFile::fake()->image('logo.jpg', 100, 100),
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNotNull($user->profile);
        $this->assertSame('This is a test bio.', $user->profile->bio);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->profile->resume_path);
        $this->assertNotNull($user->profile->logo_path);
    });

    test('email verification status is unchanged when the email address is unchanged', function () {
        $user = User::factory()->create()->assignRole('job_seeker');

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    });

    test('user can delete their account', function () {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    });

    test('correct password must be provided to delete account', function () {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    });
});
