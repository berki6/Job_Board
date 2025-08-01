<?php
use App\Models\User;

describe('Registration Test', function () {
    beforeEach(function () {
        // Seed roles
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'employer', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'job_seeker', 'guard_name' => 'web']);
    });
    test('registration screen can be rendered', function () {
        $response = $this->get('/register');

        $response->assertStatus(200);
    });

    test('new job seeker can register', function () {
        $response = $this->post('/register', [
            'name' => 'Test Seeker',
            'email' => 'seeker@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'user_role' => 'job_seeker',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('job-seeker.dashboard', absolute: false));
        $user = User::where('email', 'seeker@example.com')->first();
        $this->assertTrue($user->hasRole('job_seeker'));
    });
    test('new employer can register', function () {
        $response = $this->post('/register', [
            'name' => 'Test Employer',
            'email' => 'employer@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'user_role' => 'employer',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('employer.dashboard'));

        $user = User::where('email', 'employer@example.com')->first();
        $this->assertTrue($user->hasRole('employer'));
    });

    test('unauthenticated user cannot access job_seeker dashboard', function () {
        // Ensure unauthenticated users cannot access job seeker dashboard
        $response = $this->get('/job-seeker/dashboard');
        $response->assertRedirect('/login');
    });
    test('unauthenticated user cannot access_employer dashboard', function () {
        // Ensure unauthenticated users cannot access employer dashboard
        $response = $this->get('/company/dashboard');
        $response->assertRedirect('/login');
    });
});
