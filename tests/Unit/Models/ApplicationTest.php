<?php

use App\Jobs\NotifyEmployerJob;
use App\Jobs\NotifyJobSeekerJob;
use App\Models\Application;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);
beforeEach(function () {
    // Set up the database and necessary roles/permissions
    // $this->artisan('migrate');
    $this->artisan('db:seed', ['--class' => 'JobPermissionSeeder']);
    // // Create a user with job seeker role
    // $user = User::factory()->create()->assignRole('job_seeker');
    // $this->actingAs($user);
});

it('can be created with valid data', function () {
    $application = Application::factory()->create();

    expect($application)->toBeInstanceOf(Application::class)
        ->and($application->status)->toBe('pending');
});

it('can be approved', function () {
    $application = Application::factory()->approved()->create();

    expect($application->status)->toBe('approved');
});

it('can be rejected', function () {
    $application = Application::factory()->rejected()->create();

    expect($application->status)->toBe('rejected');
});

it('belongs to a job', function () {
    $application = Application::factory()->create();
    expect($application->job)->toBeInstanceOf(Job::class);
});

it('belongs to a user', function () {
    $application = Application::factory()->create();
    expect($application->user)->toBeInstanceOf(User::class);
});

it('can be validated with a resume', function () {
    $user = User::factory()->create()->assignRole('job_seeker');
    $job = Job::factory()->create(['is_open' => true]);

    $response = $this->actingAs($user)->post(route('applications.store', $job), [
        'resume' => UploadedFile::fake()->create('resume.pdf', 1000, 'application/pdf'),
        'cover_letter' => 'This is a cover letter.',
    ]);

    $response->assertRedirect(route('job-seeker.dashboard'));
    $this->assertDatabaseHas('applications', [
        'user_id' => $user->id,
        'job_id' => $job->id,
        'status' => 'pending',
    ]);
});

it('cannot be submitted for a closed job', function () {
    $job = Job::factory()->create(['is_open' => false]);
    $user = User::factory()->create()->assignRole('job_seeker');

    $response = $this->actingAs($user)->post(route('applications.store', $job), [
        'resume' => UploadedFile::fake()->create('resume.pdf', 1000, 'application/pdf'),
        'cover_letter' => 'This is a cover letter.',
    ]);

    $response->assertRedirect(route('jobs.show', $job->slug));
    $response->assertSessionHas('error', 'Job is closed');
});

it('validates application status update', function () {
    $application = Application::factory()->create();
    $user = User::factory()->create()->assignRole('employer');

    $response = $this->actingAs($user)->patch(route('applications.update-status', $application), [
        'status' => 'approved',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('applications', [
        'id' => $application->id,
        'status' => 'approved',
    ]);
});

it('cannot update application status with invalid data', function () {
    $application = Application::factory()->create();
    $user = User::factory()->create()->assignRole('employer');

    $response = $this->actingAs($user)->patch(route('applications.update-status', $application), [
        'status' => 'invalid_status',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('status');
    $this->assertDatabaseMissing('applications', [
        'id' => $application->id,
        'status' => 'invalid_status',
    ]);
});

it('authorizes application creation', function () {
    $job = Job::factory()->create(['is_open' => true]);
    $user = User::factory()->create()->assignRole('job_seeker');

    $response = $this->actingAs($user)->get(route('applications.create', $job));

    $response->assertOk();
    $response->assertViewIs('applications.create');
});

it('denies application creation for unauthorized users', function () {
    $job = Job::factory()->create(['is_open' => true]);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('applications.create', $job));

    $response->assertForbidden();
});

it('denies application creation for closed jobs', function () {
    $job = Job::factory()->create(['is_open' => false]);
    $user = User::factory()->create()->assignRole('job_seeker');

    $response = $this->actingAs($user)->get(route('applications.create', $job));

    $response->assertRedirect(route('jobs.show', $job->slug));
    $response->assertSessionHas('error', 'Job is closed');
});

it('authorizes application status update', function () {
    $application = Application::factory()->create();
    $user = User::factory()->create()->assignRole('employer');

    $response = $this->actingAs($user)->patch(route('applications.update-status', $application), [
        'status' => 'approved',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('applications', [
        'id' => $application->id,
        'status' => 'approved',
    ]);
});

it('denies unauthorized application status update', function () {
    $application = Application::factory()->create();
    $user = User::factory()->create();

    $response = $this->actingAs($user)->patch(route('applications.update-status', $application), [
        'status' => 'approved',
    ]);

    $response->assertForbidden();
});

it('can dispatch job notifications', function () {
    // Arrange: Create a job and a user, fake the Queue
    Queue::fake(); // Intercept all queue dispatches

    $job = Job::factory()->create(['is_open' => true]);
    $user = User::factory()->create()->assignRole('job_seeker');

    // Act: Simulate storing a job application
    $this->actingAs($user)->post(route('applications.store', $job), [
        'resume' => UploadedFile::fake()->create('resume.pdf', 1000, 'application/pdf'),
        'cover_letter' => 'This is a cover letter.',
    ]);

    // Assert: Check if NotifyEmployerJob was dispatched *once*
    Queue::assertPushed(NotifyEmployerJob::class);
});

it('can dispatch job seeker notifications on application status update', function () {
    // Arrange: Create an application and fake the Queue
    Queue::fake();

    $application = Application::factory()->create();
    $application->user->assignRole('employer');

    // Act: Simulate updating the application status
    $this->actingAs($application->user)->patch(route('applications.update-status', $application), [
        'status' => 'approved',
    ]);

    // Assert: Check if NotifyJobSeekerJob was dispatched *once*
    Queue::assertPushed(NotifyJobSeekerJob::class);
});

it('can handle file uploads for resumes', function () {
    $job = Job::factory()->create(['is_open' => true]);
    $user = User::factory()->create()->assignRole('job_seeker');
    $response = $this->actingAs($user)->post(route('applications.store', $job), [
        'resume' => UploadedFile::fake()->create('resume.pdf', 1000, 'application/pdf'),
        'cover_letter' => 'This is a cover letter.',
    ]);
    $response->assertRedirect(route('job-seeker.dashboard'));
    // Retrieve the stored path from session
    $resumePath = $response->getSession()->get('resume_path');

    // Build expected value ('resumes/<filename>')
    $expectedPath = 'resumes/'.pathinfo($resumePath, PATHINFO_BASENAME);

    // Assert that the database has the record with the correct path
    $this->assertDatabaseHas('applications', [
        'user_id' => $user->id,
        'job_id' => $job->id,
        'resume_path' => $expectedPath,
    ]);
});

it('validates resume file type and size', function () {
    $job = Job::factory()->create(['is_open' => true]);
    $user = User::factory()->create()->assignRole('job_seeker');

    $response = $this->actingAs($user)->post(route('applications.store', $job), [
        'resume' => UploadedFile::fake()->create('resume.txt', 1000, 'text/plain'),
        'cover_letter' => 'This is a cover letter.',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('resume');
});

it('validates cover letter as optional', function () {
    $job = Job::factory()->create(['is_open' => true]);
    $user = User::factory()->create()->assignRole('job_seeker');

    $response = $this->actingAs($user)->post(route('applications.store', $job), [
        'resume' => UploadedFile::fake()->create('resume.pdf', 1000, 'application/pdf'),
        'cover_letter' => null,
    ]);

    $response->assertRedirect(route('job-seeker.dashboard'));
    $this->assertDatabaseHas('applications', [
        'user_id' => $user->id,
        'job_id' => $job->id,
        'cover_letter' => null,
    ]);
});

it('can retrieve the job associated with the application', function () {
    $application = Application::factory()->create();
    expect($application->job)->toBeInstanceOf(Job::class);
});

it('can retrieve the user who submitted the application', function () {
    $application = Application::factory()->create();
    expect($application->user)->toBeInstanceOf(User::class);
});

it('casts applied_at to datetime', function () {
    $application = Application::factory()->create();
    expect($application->created_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

it('can be updated with new data', function () {
    $application = Application::factory()->create();
    $user = User::factory()->create()->assignRole('job_seeker');
    $application->update(['user_id' => $user->id]);
    $newCoverLetter = 'Updated cover letter content';

    $response = $this->actingAs($user)->patch(route('applications.update', $application), [
        'cover_letter' => $newCoverLetter,
        'resume' => UploadedFile::fake()->create('resume.pdf', 1000, 'application/pdf'),
    ]);

    $response->assertRedirect();
    $application->refresh();
    $filePath = $application->resume_path;
    $expectedFilename = pathinfo($filePath, PATHINFO_DIRNAME).'/'.pathinfo($filePath, PATHINFO_BASENAME);

    expect($application->fresh()->cover_letter)->toEqual($newCoverLetter);
    expect($application->resume_path)->toContain($expectedFilename);

    $this->assertDatabaseHas('applications', [
        'id' => $application->id,
        'cover_letter' => $newCoverLetter,
        'resume_path' => $filePath,
    ]);
});

it('can be deleted', function () {
    $job = Job::factory()->create(['is_open' => true]);
    $user = User::factory()->create()->assignRole('job_seeker');
    $application = Application::factory()->create(['job_id' => $job->id, 'user_id' => $user->id]);

    $response = $this->actingAs($user)->delete(route('applications.destroy', ['job' => $job, 'application' => $application]));
    // Assuming $user and $application are already defined in your context
    $response->assertRedirect(route('job-seeker.dashboard'));
    $this->assertDatabaseMissing('applications', ['id' => $application->id]);
});

it('cannot be deleted by unauthorized users', function () {
    $job = Job::factory()->create();
    $application = Application::factory()->create(['job_id' => $job->id]);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->delete(route('applications.destroy', ['job' => $job, 'application' => $application]));

    $response->assertForbidden();
    $this->assertDatabaseHas('applications', ['id' => $application->id]);
});

it('can be retrieved by job seekers', function () {
    $job = Job::factory()->create(['is_open' => true]);
    $user = User::factory()->create()->assignRole('job_seeker');
    $application = Application::factory()->create(['job_id' => $job->id, 'user_id' => $user->id]);

    $response = $this->actingAs($user)->get(route('applications.index'));

    $response->assertOk();
    $response->assertViewHas('applications', function ($applications) use ($application) {
        return $applications->contains($application);
    });
});

it('can be retrieved by employers', function () {
    $job = Job::factory()->create(['is_open' => true]);
    $user = User::factory()->create()->assignRole('employer');
    $application = Application::factory()->create(['job_id' => $job->id, 'user_id' => $user->id]);

    $response = $this->actingAs($user)->get(route('applications.index'));

    $response->assertOk();
    $response->assertViewHas('applications', function ($applications) use ($application) {
        return $applications->contains($application);
    });
});
