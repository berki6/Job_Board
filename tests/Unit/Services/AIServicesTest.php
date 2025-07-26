<?php

use App\Contracts\GeminiClientInterface;
use App\Services\AIServices;
use App\Models\User;
use App\Models\Job;
use App\Models\Profile;

describe('AIServices', function () {
    beforeEach(function () {
        // Mock the wrapper interface instead of the final Gemini class
        $this->mockGeminiClient = Mockery::mock(GeminiClientInterface::class);
        $this->aiService = new AIServices($this->mockGeminiClient);
    });

    it('throws exception when user has no profile', function () {
        $user = User::factory()->create();
        $job = Job::factory()->create();

        expect(fn() => $this->aiService->generateCoverLetter($job, $user))
            ->toThrow(Exception::class, 'User profile not found');
    });

    it('generates cover letter with default prompt', function () {
        $user = User::factory()->create();
        Profile::factory()->create([
            'user_id' => $user->id,
            'bio' => 'Experienced developer',
            'skills' => ['PHP', 'Laravel']
        ]);

        $job = Job::factory()->create([
            'title' => 'Senior Developer',
            'description' => 'We need a senior developer'
        ]);

        // Prepare expected default prompt
        $expectedPrompt = "Generate a cover letter for the following job:\n" .
            "Title: Senior Developer\nDescription: We need a senior developer\n" .
            "User: {$user->name}\nBio: Experienced developer\nSkills: PHP, Laravel";

        // Mock Gemini client response
        $this->mockGeminiClient
            ->shouldReceive('generateText')
            ->once()
            ->with(Mockery::on(fn($prompt) => str_contains($prompt, 'Senior Developer')))
            ->andReturn('Generated cover letter content');

        $result = $this->aiService->generateCoverLetter($job, $user);

        expect($result)->toBe('Generated cover letter content');
    });

    it('generates cover letter with custom template', function () {
        $user = User::factory()->create();
        Profile::factory()->create(['user_id' => $user->id]);
        $job = Job::factory()->create();

        $customTemplate = 'Custom template for cover letter';

        // Mock Gemini client response
        $this->mockGeminiClient
            ->shouldReceive('generateText')
            ->once()
            ->with($customTemplate)
            ->andReturn('Custom cover letter');

        $result = $this->aiService->generateCoverLetter($job, $user, $customTemplate);

        expect($result)->toBe('Custom cover letter');
    });

    it('creates correct default prompt with user skills', function () {
        $user = User::factory()->create(['name' => 'John Doe']);
        Profile::factory()->create([
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
        Profile::factory()->create([
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
