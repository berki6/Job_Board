<?php

describe('Profile Model', function () {
    it('can create a profile with skills', function () {
        $user = \App\Models\User::factory()->create();
        $profile = \App\Models\Profile::factory()->create([
            'user_id' => $user->id,
            'bio' => 'Experienced software developer.',
            'skills' => ['PHP', 'JavaScript']
        ]);

        expect($profile->bio)->toBe('Experienced software developer.')
            ->and($profile->skills)->toBe(['PHP', 'JavaScript']);
    });

    it('belongs to a user', function () {
        $user = \App\Models\User::factory()->create();
        $profile = \App\Models\Profile::factory()->create(['user_id' => $user->id]);

        expect($profile->user)->toBeInstanceOf(\App\Models\User::class)
            ->and($profile->user->id)->toBe($user->id);
    });

    it('can have a resume path', function () {
        $profile = \App\Models\Profile::factory()->create(['resume_path' => 'resumes/johndoe.pdf']);

        expect($profile->resume_path)->toBe('resumes/johndoe.pdf');
    });

    it('handles null skills gracefully', function () {
        $profile = \App\Models\Profile::factory()->create(['skills' => null]);

        expect($profile->skills)->toBeNull();
    });

    it('casts skills as array', function () {
        $profile = \App\Models\Profile::factory()->create([
            'skills' => ['Laravel', 'Vue.js'],
        ]);

        expect($profile->skills)->toBeArray()
            ->and($profile->skills)->toContain('Laravel', 'Vue.js');
    });
});

