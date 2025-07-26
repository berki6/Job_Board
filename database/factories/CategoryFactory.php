<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->randomElement([
            'Technology', 'Marketing', 'Sales', 'Design', 'Customer Support',
            'Human Resources', 'Finance', 'Operations', 'Product', 'Engineering'
        ]);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
        ];
    }
}
