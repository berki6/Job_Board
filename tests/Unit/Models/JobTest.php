<?php

use App\Models\Application;
use App\Models\Category;
use App\Models\Job;
use App\Models\User;

describe('Job Model', function () {
    it('can create a job', function () {
        $company = User::factory()->create(['name' => 'Test Company']);
        $job = Job::factory()->create([
            'user_id' => $company->id,
            'title' => 'Senior Developer',
            'description' => 'Great opportunity for a senior developer',
            'location' => 'Remote',
            'salary' => 90000,
            'status' => 'open',
        ]);

        expect($job->title)->toBe('Senior Developer')
            ->and($job->location)->toBe('Remote')
            ->and($job->salary)->toBe('90000.00')
            ->and($job->status)->toBe('open');
    });

    it('uses the correct table name', function () {
        $job = new Job;

        expect($job->getTable())->toBe('jobs_listing');
    });

    it('belongs to a company', function () {
        $company = User::factory()->create(['name' => 'Test Company']);
        $job = Job::factory()->create(['user_id' => $company->id]);

        expect($job->company)->toBeInstanceOf(User::class)
            ->and($job->company->name)->toBe('Test Company');
    });

    it('can belong to a category', function () {
        $category = Category::factory()->create(['name' => 'Technology']);
        $job = Job::factory()->create(['category_id' => $category->id]);

        expect($job->category)->toBeInstanceOf(Category::class)
            ->and($job->category->name)->toBe('Technology');
    });

    it('has many applications', function () {
        $job = Job::factory()->create();
        $user = User::factory()->create();

        Application::factory()->create([
            'job_id' => $job->id,
            'user_id' => $user->id,
        ]);

        expect($job->applications)->toHaveCount(1)
            ->and($job->applications->first())->toBeInstanceOf(Application::class);
    });

    it('casts salary as decimal', function () {
        $job = Job::factory()->create(['salary' => 75000]);

        expect($job->salary)->toBeString()
            ->and($job->salary)->toBe('75000.00');
    });

    it('can be closed', function () {
        $job = Job::factory()->closed()->create();

        expect($job->status)->toBe('closed');
    });

    it('filters open jobs correctly', function () {
        Job::factory()->create(['status' => 'open']);
        Job::factory()->create(['status' => 'closed']);

        $openJobs = Job::where('status', 'open')->get();

        expect($openJobs)->toHaveCount(1)
            ->and($openJobs->first()->status)->toBe('open');
    });
});
