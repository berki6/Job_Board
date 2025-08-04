<?php

use App\Models\Application;
use App\Models\Category;
use App\Models\Job;
use App\Models\JobType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);
beforeEach(function () {
    // Seed roles
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'employer', 'guard_name' => 'web']);
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'job_seeker', 'guard_name' => 'web']);
});

describe('Job Model', function () {
    it('can create a job', function () {
        $company = User::factory()->create(['name' => 'Test Company'])->assignRole('employer');
        $job = Job::factory()->create([
            'user_id' => $company->id,
            'title' => 'Senior Developer',
            'description' => 'Great opportunity for a senior developer',
            'location' => 'Remote',
            'salary_min' => 80000,
            'salary_max' => 90000,
            'status' => 'published',
        ]);

        expect($job->title)->toBe('Senior Developer')
            ->and($job->location)->toBe('Remote')
            ->and((float) $job->salary_min)->toBe(80000.0)
            ->and((float) $job->salary_max)->toBe(90000.0)
            ->and($job->status)->toBe('published');
    });

    it('uses the correct table name', function () {
        $job = new Job;

        expect($job->getTable())->toBe('jobs_listing');
    });

    it('belongs to a company', function () {
        $company = User::factory()->create(['name' => 'Test Company'])->assignRole('employer');
        $job = Job::factory()->create(['user_id' => $company->id]);

        expect($job->user)->toBeInstanceOf(User::class)->and($job->user->name)->toBe('Test Company');
    });

    it('can belong to a category', function () {
        $category = Category::factory()->create(['name' => 'Technology']);
        $job = Job::factory()->create(['category_id' => $category->id]);

        expect($job->category)->toBeInstanceOf(Category::class)
            ->and($job->category->name)->toBe('Technology');
    });

    it('has many applications', function () {
        $user = User::factory()->create()->assignRole('job_seeker');
        $job = Job::factory()->create(['user_id' => $user->id]);

        Application::factory()->create([
            'job_id' => $job->id,
            'user_id' => $user->id,
        ]);

        expect($job->applications)->toHaveCount(1)
            ->and($job->applications->first())->toBeInstanceOf(Application::class);
    });

    it('casts salary as decimal', function () {
        $job = Job::factory()->create([
            'salary_min' => 75000,
            'salary_max' => 75000,
        ]);

        expect($job->salary_min)->toBeString()
            ->and($job->salary_min)->toBe('75000.00')
            ->and($job->salary_max)->toBeString()
            ->and($job->salary_max)->toBe('75000.00');
    });

    it('can be closed', function () {
        $job = Job::factory()->closed()->create();

        expect($job->is_open)->toBe(false);
    });

    it('can be opened', function () {
        $job = Job::factory()->create(['is_open' => true]);

        expect($job->is_open)->toBe(true);
    });

    it('generates a unique slug on creation', function () {
        $job1 = Job::factory()->create(['title' => 'Unique Job Title']);
        $job2 = Job::factory()->create(['title' => 'Unique Job Title']);

        expect($job1->slug)->toBe('unique-job-title')
            ->and($job2->slug)->toBe('unique-job-title-1');
    });

    it('updates slug when title changes', function () {
        $job = Job::factory()->create(['title' => 'Initial Title']);

        $job->title = 'Updated Title';
        $job->save();

        expect($job->slug)->toBe('updated-title');
    });

    it('filters open jobs correctly', function () {
        Job::factory()->create(['is_open' => true]);
        Job::factory()->create(['is_open' => false]);

        $openJobs = Job::where('is_open', true)->get();

        expect($openJobs)->toHaveCount(1)->and($openJobs->first()->is_open)->toBe(true);
    });

    it('filters closed jobs correctly', function () {
        Job::factory()->create(['is_open' => false]);
        Job::factory()->create(['is_open' => true]);

        $closedJobs = Job::where('is_open', false)->get();

        expect($closedJobs)->toHaveCount(1)->and($closedJobs->first()->is_open)->toBe(false);
    });

    it('can be searched by title', function () {
        $job1 = Job::factory()->create(['title' => 'Software Engineer']);
        $job2 = Job::factory()->create(['title' => 'Data Scientist']);
        // Make sure the search index is up-to-date
        $job1->searchable();
        $job2->searchable();

        $searchResults = Job::search('Software')->get();

        expect($searchResults)->toHaveCount(1)->and($searchResults->first()->id)->toBe($job1->id);
    });

    it('can be saved by users', function () {
        $user = User::factory()->create()->assignRole('job_seeker');
        $job = Job::factory()->create();

        expect(DB::table('users')->where('id', $user->id)->exists())->toBeTrue();
        expect(DB::table('jobs_listing')->where('id', operator: $job->id)->exists())->toBeTrue();

        $job->savedBy()->attach($user->id);

        expect($job->savedBy)->toHaveCount(1)->and($job->savedBy->first()->id)->toBe($user->id);
    });

    it('can be unsaved by users', function () {
        $user = User::factory()->create()->assignRole('job_seeker');
        $job = Job::factory()->create();

        $job->savedBy()->attach($user->id);
        $job->savedBy()->detach($user->id);

        expect($job->savedBy)->toHaveCount(0);
    });

    it('can be featured', function () {
        $job = Job::factory()->create(['is_featured' => true]);

        expect($job->is_featured)->toBe(true);
    });

    it('can be non-featured', function () {
        $job = Job::factory()->create(['is_featured' => false]);

        expect($job->is_featured)->toBe(false);
    });

    it('can be filtered by category', function () {
        $category = Category::factory()->create(['name' => 'Engineering']);
        $job1 = Job::factory()->create(['category_id' => $category->id]);
        $job2 = Job::factory()->create(['category_id' => $category->id]);

        $filteredJobs = Job::where('category_id', $category->id)->get();

        expect($filteredJobs)->toHaveCount(2)
            ->and($filteredJobs->first()->category_id)->toBe($category->id);
    });

    it('can be filtered by job type', function () {
        $jobType = JobType::factory()->create(['name' => 'Full-time']);
        $job1 = Job::factory()->create(['job_type_id' => $jobType->id]);
        $job2 = Job::factory()->create(['job_type_id' => $jobType->id]);

        $filteredJobs = Job::where('job_type_id', $jobType->id)->get();

        expect($filteredJobs)->toHaveCount(2)
            ->and($filteredJobs->first()->job_type_id)->toBe($jobType->id);
    });

    it('can be filtered by salary range', function () {
        $job1 = Job::factory()->withSalaryRange(50000, 70000)->create();
        $job2 = Job::factory()->withSalaryRange(80000, 100000)->create();

        $filteredJobs = Job::whereBetween('salary_min', [60000, 90000])->get();

        expect($filteredJobs)->toHaveCount(1)->and($filteredJobs->first()->id)->toBe($job2->id);
    });

    it('can be filtered by salary range explicitly', function () {
        $job1 = Job::factory()->withSalaryRange(50000, 70000)->create();
        $job2 = Job::factory()->withSalaryRange(80000, 100000)->create();

        // Filter jobs whose salary range overlaps with 60000 - 90000
        $filteredJobs = Job::where(function ($query) {
            $query->where('salary_min', '<=', 90000) // Minimum salary is less than or equal to the upper bound of the range
                ->where('salary_max', '>=', 60000); // Maximum salary is greater than or equal to the lower bound of the range
        })->get();

        expect($filteredJobs)->toHaveCount(2); // Both Jobs

        // Add to check specific jobs.
        expect($filteredJobs[0]->id)->toBe($job1->id);
        expect($filteredJobs[1]->id)->toBe($job2->id);
    });

    it('can be filtered by remote jobs', function () {
        $remoteJob = Job::factory()->create(['remote' => true]);
        $nonRemoteJob = Job::factory()->create(['remote' => false]);

        $remoteJobs = Job::where('remote', true)->get();

        expect($remoteJobs)->toHaveCount(1)
            ->and($remoteJobs->first()->id)->toBe($remoteJob->id);
    });

    it('can be filtered by job status', function () {
        $publishedJob = Job::factory()->create(['status' => 'published']);
        $draftJob = Job::factory()->create(['status' => 'draft']);

        $publishedJobs = Job::where('status', 'published')->get();

        expect($publishedJobs)->toHaveCount(1)
            ->and($publishedJobs->first()->id)->toBe($publishedJob->id);
    });

    it('can be filtered by job title', function () {
        $job1 = Job::factory()->create(['title' => 'Senior Developer']);
        $job2 = Job::factory()->create(['title' => 'Junior Developer']);

        $filteredJobs = Job::where('title', 'like', '%Developer%')->get();

        expect($filteredJobs)->toHaveCount(2)
            ->and($filteredJobs->first()->id)->toBe($job1->id);
    });

    it('can be filtered by job description', function () {
        $job1 = Job::factory()->create(['description' => 'Great opportunity for a developer']);
        $job2 = Job::factory()->create(['description' => 'Looking for a data scientist']);

        $filteredJobs = Job::where('description', 'like', '%developer%')->get();

        expect($filteredJobs)->toHaveCount(1)
            ->and($filteredJobs->first()->id)->toBe($job1->id);
    });

    it('can be filtered by job location', function () {
        $job1 = Job::factory()->create(['location' => 'Remote']);
        $job2 = Job::factory()->create(['location' => 'New York']);

        $filteredJobs = Job::where('location', 'Remote')->get();

        expect($filteredJobs)->toHaveCount(1)
            ->and($filteredJobs->first()->id)->toBe($job1->id);
    });
});
