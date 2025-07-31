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
            'user_id' => User::factory()->create()->assignRole('employer')->id,
            'job_type_id' => JobType::firstOrCreate(['name' => 'Full-time'])->id,
            'category_id' => Category::factory()->create()->id,
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
        return $this->state(fn (array $attributes) => [
            'status' => 'closed',
        ]);
    }

    public function withSalaryRange(int $min, int $max): static
    {
        return $this->state(fn (array $attributes) => [
            'salary' => $this->faker->numberBetween($min, $max),
        ]);
    }
}
