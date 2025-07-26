<?php

use App\Services\AIServices;
use App\Models\User;
use App\Models\Job;
use App\Models\Profile;
use Gemini\Laravel\Facades\Gemini;

describe('AIServices', function () {
    beforeEach(function () {
        $this->aiService = new AIServices();
    });

    it('throws exception when user has no profile', function () {
        $user = User::factory()->create();
        $job = Job::factory()->create();

        expect(fn() => $this->aiService->generateCoverLetter($job, $user))
            ->toThrow(Exception::class, 'User profile not found');
    });

    it('generates cover letter with default prompt', function () {
        // Mock Gemini response
        Gemini::shouldReceive('geminiPro->generateContent')
            ->once()
            ->andReturn((object) ['text' => fn() => 'Generated cover letter content']);

        $user = User::factory()->create();
        $profile = Profile::factory()->create([
            'user_id' => $user->id,
            'bio' => 'Experienced developer',
            'skills' => ['PHP', 'Laravel']
        ]);

        $job = Job::factory()->create([
            'title' => 'Senior Developer',
            'description' => 'We need a senior developer'
        ]);

        $result = $this->aiService->generateCoverLetter($job, $user);

        expect($result)->toBe('Generated cover letter content');
    });

    it('generates cover letter with custom template', function () {
        // Mock Gemini response
        Gemini::shouldReceive('geminiPro->generateContent')
            ->once()
            ->with('Custom template for cover letter')
            ->andReturn((object) ['text' => fn() => 'Custom cover letter']);

        $user = User::factory()->create();
        Profile::factory()->create(['user_id' => $user->id]);
        $job = Job::factory()->create();

        $result = $this->aiService->generateCoverLetter($job, $user, 'Custom template for cover letter');

        expect($result)->toBe('Custom cover letter');
    });

    it('creates correct default prompt with user skills', function () {
        $user = User::factory()->create(['name' => 'John Doe']);
        $profile = Profile::factory()->create([
            'user_id' => $user->id,
            'bio' => 'Full stack developer with 5 years experience',
            'skills' => ['PHP', 'JavaScript', 'React']
        ]);

        $job = Job::factory()->create([
            'title' => 'Frontend Developer',
            'description' => 'Join our team as a frontend developer'
        ]);

        $reflection = new ReflectionClass($this->aiService);
        $method = $reflection->getMethod('defaultPrompt');
        $method->setAccessible(true);

        $prompt = $method->invoke($this->aiService, $job, $user);

        expect($prompt)->toContain('Frontend Developer')
            ->and($prompt)->toContain('Join our team as a frontend developer')
            ->and($prompt)->toContain('John Doe')
            ->and($prompt)->toContain('PHP, JavaScript, React')
            ->and($prompt)->toContain('Full stack developer with 5 years experience');
    });

    it('handles user with no skills', function () {
        $user = User::factory()->create(['name' => 'Jane Doe']);
        $profile = Profile::factory()->create([
            'user_id' => $user->id,
            'bio' => 'New developer',
            'skills' => null
        ]);

        $job = Job::factory()->create([
            'title' => 'Junior Developer',
            'description' => 'Entry level position'
        ]);

        $reflection = new ReflectionClass($this->aiService);
        $method = $reflection->getMethod('defaultPrompt');
        $method->setAccessible(true);

        $prompt = $method->invoke($this->aiService, $job, $user);

        expect($prompt)->toContain('Not specified')
            ->and($prompt)->toContain('Jane Doe')
            ->and($prompt)->toContain('New developer');
    });
});
