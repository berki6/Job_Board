<?php

use App\Models\User;

describe('AutoApplyController', function () {
    beforeEach(function () {
        // Seed roles and permissions
        $this->artisan('db:seed', ['--class' => 'JobPermissionSeeder']);
    });
    it('requires authentication to access auto apply page', function () {
        $response = $this->get(route('auto.apply'));

        $response->assertRedirect(route('login'));
    });

    it('requires premium middleware to access auto apply page', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('auto.apply'));

        $response->assertRedirect(route('subscribe'))
            ->assertSessionHas('error', 'Upgrade to premium to use this feature.');
    });

    it('shows auto apply page for premium users', function () {
        $user = createPremiumUser()->assignRole('job_seeker');

        $response = $this->actingAs($user)->get(route('auto.apply'));

        $response->assertStatus(200)
            ->assertViewIs('auto-apply')
            ->assertViewHas('preferences');
    });

    it('shows default preferences for new users', function () {
        $user = createPremiumUser()->assignRole('job_seeker');

        $response = $this->actingAs($user)->get(route('auto.apply'));

        $response->assertStatus(200);

        $preferences = $response->viewData('preferences');
        expect($preferences->auto_apply_enabled)->toBeFalse()
            ->and($preferences->job_titles)->toBe([])
            ->and($preferences->locations)->toBe([]);
    });

    it('shows existing preferences for users', function () {
        $user = createPremiumUser()->assignRole('job_seeker');
        $preferences = createAutoApplyPreferences($user, [
            'auto_apply_enabled' => true,
            'job_titles' => ['Developer'],
            'locations' => ['Remote'],
        ]);

        $response = $this->actingAs($user)->get(route('auto.apply'));

        $response->assertStatus(200);

        $viewPreferences = $response->viewData('preferences');
        expect($viewPreferences->auto_apply_enabled)->toBeTrue()
            ->and($viewPreferences->job_titles)->toBe(['Developer'])
            ->and($viewPreferences->locations)->toBe(['Remote']);
    });

    it('can update auto apply preferences', function () {
        $user = createPremiumUser()->assignRole('job_seeker');

        $response = $this->actingAs($user)->post(route('auto.apply.update'), [
            'job_titles' => '["Senior Developer", "Lead Engineer"]',
            'locations' => '["Remote", "San Francisco"]',
            'salary_min' => 80000,
            'salary_max' => 120000,
            'cover_letter_template' => 'Custom template',
        ]);

        $response->assertRedirect()
            ->assertSessionHas('success', 'Preferences updated successfully.');

        $preferences = $user->fresh()->autoApplyPreference;
        expect($preferences->job_titles)->toBe(['Senior Developer', 'Lead Engineer'])
            ->and($preferences->locations)->toBe(['Remote', 'San Francisco'])
            ->and($preferences->salary_min)->toBe(80000)
            ->and($preferences->salary_max)->toBe(120000)
            ->and($preferences->cover_letter_template)->toBe('Custom template');
    });

    it('validates update request data', function () {
        $user = createPremiumUser()->assignRole('job_seeker');

        $response = $this->actingAs($user)->post(route('auto.apply.update'), [
            'salary_min' => 'invalid',
            'salary_max' => 'invalid',
        ]);

        $response->assertSessionHasErrors(['salary_min', 'salary_max']);
    });

    it('can toggle auto apply on', function () {
        $user = createPremiumUser()->assignRole('job_seeker');

        $response = $this->actingAs($user)->get(route('auto.apply.toggle'));

        $response->assertRedirect()
            ->assertSessionHas('success', 'Auto-Apply status updated.');

        $preferences = $user->fresh()->autoApplyPreference;
        expect($preferences->auto_apply_enabled)->toBeTrue();
    });

    it('can toggle auto apply off', function () {
        $user = createPremiumUser()->assignRole('job_seeker');
        createAutoApplyPreferences($user, ['auto_apply_enabled' => true]);

        $response = $this->actingAs($user)->get(route('auto.apply.toggle'));

        $response->assertRedirect()
            ->assertSessionHas('success', 'Auto-Apply status updated.');

        $preferences = $user->fresh()->autoApplyPreference;
        expect($preferences->auto_apply_enabled)->toBeFalse();
    });

    it('creates preferences when toggling for new users', function () {
        $user = createPremiumUser()->assignRole('job_seeker');

        expect($user->autoApplyPreference)->toBeNull();

        $response = $this->actingAs($user)->get(route('auto.apply.toggle'));

        $response->assertRedirect();

        $preferences = $user->fresh()->autoApplyPreference;
        expect($preferences)->not->toBeNull()
            ->and($preferences->auto_apply_enabled)->toBeTrue();
    });

    it('handles JSON parsing errors gracefully', function () {
        $user = createPremiumUser()->assignRole('job_seeker');

        $response = $this->actingAs($user)->post(route('auto.apply.update'), [
            'job_titles' => 'invalid json',
            'locations' => 'invalid json',
        ]);

        // Should not throw an error, should handle gracefully
        $response->assertRedirect();
    });
});
