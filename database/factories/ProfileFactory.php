<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProfileFactory extends Factory
{
    protected $model = Profile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'bio' => $this->faker->paragraph(3),
            'phone' => $this->faker->phoneNumber(),
            'website' => $this->faker->optional()->url(),
            'logo_path' => null,
            'resume_path' => $this->faker->optional()->filePath(),
            'skills' => $this->faker->randomElements([
                'PHP', 'JavaScript', 'Python', 'Java', 'C++', 'React', 'Vue.js', 
                'Angular', 'Laravel', 'Django', 'Node.js', 'Docker', 'AWS'
            ], $this->faker->numberBetween(3, 8)),
        ];
    }

    public function withResume(): static
    {
        return $this->state(fn (array $attributes) => [
            'resume_path' => 'resumes/' . $this->faker->uuid() . '.pdf',
        ]);
    }

    public function withoutResume(): static
    {
        return $this->state(fn (array $attributes) => [
            'resume_path' => null,
        ]);
    }
}
