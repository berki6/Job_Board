<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Job;
use App\Models\JobType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class JobFactory extends Factory
{
    protected $model = Job::class;

    public function definition(): array
    {
        $title = $this->faker->jobTitle();

        return [
            'user_id' => User::factory(),
            'job_type_id' => JobType::factory(),
            'category_id' => Category::factory(),
            'title' => $this->faker->jobTitle(),
            'description' => $this->faker->paragraph(5),
            'location' => $this->faker->randomElement(['Remote', 'New York', 'San Francisco', 'London', 'Berlin']),
            'salary_min' => $this->faker->numberBetween(40000, 150000),
            'salary_max' => $this->faker->numberBetween(40000, 150000),
            'remote' => $this->faker->boolean(),
            'status' => 'published',
            'is_open' => true,
            'is_featured' => false,
            'application_method' => 'form',
            'external_link' => null,
            'slug' => Str::slug($title),
        ];
    }

    public function closed(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_open' => tap(false, function ($isOpen) {
                dump("Setting is_open to: " . ($isOpen ? 'true' : 'false'));
            }),
        ]);
    }

    public function withSalaryRange(int $min, int $max): static
    {
        return $this->state(fn(array $attributes) => [
            'salary_min' => $min,
            'salary_max' => $max,
        ]);
    }
}
