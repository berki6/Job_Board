<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Job;
use App\Models\JobType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class JobFactory extends Factory
{
    protected $model = Job::class;

    public function definition(): array
    {
        $jobType = JobType::firstOrCreate(['name' => 'Full-time']);

        return [
            'company_id' => User::factory()->create()->id,
            'category_id' => Category::factory()->create()->id,
            'job_type_id' => $jobType->id,
            'title' => $this->faker->jobTitle(),
            'description' => $this->faker->paragraph(5),
            'location' => $this->faker->randomElement(['Remote', 'New York', 'San Francisco', 'London', 'Berlin']),
            'salary' => $this->faker->numberBetween(40000, 150000),
            'status' => 'open',
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
