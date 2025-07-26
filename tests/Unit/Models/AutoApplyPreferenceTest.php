<?php

use App\Models\AutoApplyPreference;
use App\Models\User;

describe('AutoApplyPreference Model', function () {
    it('can create auto apply preferences', function () {
        $user = User::factory()->create();
        $preferences = AutoApplyPreference::factory()->create([
            'user_id' => $user->id,
            'auto_apply_enabled' => true,
            'job_titles' => ['Developer', 'Engineer'],
            'locations' => ['Remote', 'New York'],
            'salary_min' => 50000,
            'salary_max' => 100000,
        ]);

        expect($preferences->auto_apply_enabled)->toBeTrue()
            ->and($preferences->job_titles)->toBe(['Developer', 'Engineer'])
            ->and($preferences->locations)->toBe(['Remote', 'New York'])
            ->and($preferences->salary_min)->toBe(50000)
            ->and($preferences->salary_max)->toBe(100000);
    });

    it('belongs to a user', function () {
        $user = User::factory()->create();
        $preferences = AutoApplyPreference::factory()->create(['user_id' => $user->id]);

        expect($preferences->user)->toBeInstanceOf(User::class)
            ->and($preferences->user->id)->toBe($user->id);
    });

    it('casts auto_apply_enabled as boolean', function () {
        $preferences = AutoApplyPreference::factory()->create([
            'auto_apply_enabled' => '1',
        ]);

        expect($preferences->auto_apply_enabled)->toBeTrue()
            ->and($preferences->auto_apply_enabled)->toBeBool();
    });

    it('casts job_titles as array', function () {
        $preferences = AutoApplyPreference::factory()->create([
            'job_titles' => ['Developer', 'Designer'],
        ]);

        expect($preferences->job_titles)->toBeArray()
            ->and($preferences->job_titles)->toContain('Developer', 'Designer');
    });

    it('casts locations as array', function () {
        $preferences = AutoApplyPreference::factory()->create([
            'locations' => ['Remote', 'San Francisco'],
        ]);

        expect($preferences->locations)->toBeArray()
            ->and($preferences->locations)->toContain('Remote', 'San Francisco');
    });

    it('can be disabled', function () {
        $preferences = AutoApplyPreference::factory()->create([
            'auto_apply_enabled' => false,
        ]);

        expect($preferences->auto_apply_enabled)->toBeFalse();
    });

    it('handles null arrays gracefully', function () {
        $preferences = AutoApplyPreference::factory()->create([
            'job_titles' => null,
            'locations' => null,
        ]);

        expect($preferences->job_titles)->toBeNull()
            ->and($preferences->locations)->toBeNull();
    });
});
