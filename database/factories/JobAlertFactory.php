<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\JobType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\JobAlert>
 */
class JobAlertFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'keywords' => $this->faker->words(3, true),
            'location' => $this->faker->city,
            'category_id' => Category::factory(),
            'job_type_id' => JobType::factory(),
            'frequency' => $this->faker->randomElement(['daily', 'weekly']),
        ];
    }
}
