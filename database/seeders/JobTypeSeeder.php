<?php

namespace Database\Seeders;

use App\Models\JobType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class JobTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = ['Full-time', 'Part-time', 'Remote'];
        foreach ($types as $type) {
            JobType::firstOrCreate(['name' => $type]);
        }
    }
}
