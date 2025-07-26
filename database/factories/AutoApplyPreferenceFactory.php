<?php

namespace Database\Factories;

use App\Models\AutoApplyPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AutoApplyPreferenceFactory extends Factory
{
    protected $model = AutoApplyPreference::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'auto_apply_enabled' => $this->faker->boolean(),
            'job_titles' => $this->faker->randomElements([
                'Software Developer', 'Frontend Developer', 'Backend Developer',
                'Full Stack Developer', 'Senior Developer', 'Lead Developer'
            ], $this->faker->numberBetween(1, 3)),
            'locations' => $this->faker->randomElements([
                'Remote', 'New York', 'San Francisco', 'London', 'Berlin'
            ], $this->faker->numberBetween(1, 3)),
            'salary_min' => $this->faker->optional()->numberBetween(40000, 80000),
            'salary_max' => $this->faker->optional()->numberBetween(80000, 150000),
            'cover_letter_template' => $this->faker->optional()->paragraph(),
        ];
    }

    public function enabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'auto_apply_enabled' => true,
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'auto_apply_enabled' => false,
        ]);
    }
}
