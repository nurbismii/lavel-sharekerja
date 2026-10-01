<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\JobPosting;
use Illuminate\Database\Eloquent\Factories\Factory;

class JobPostingFactory extends Factory
{
    protected $model = JobPosting::class;

    public function definition(): array
    {
        return ['country_code' => 'ID', 'company_id' => Company::factory(), 'title' => 'Software Engineer', 'slug' => fake()->unique()->slug(), 'category' => 'Teknologi', 'employment_type' => 'Full-time', 'level' => 'Junior', 'vacancies' => 1, 'location' => 'Makassar', 'work_arrangement' => 'ON_SITE', 'salary_min' => 7000000, 'salary_max' => 10000000, 'salary_visible' => false, 'salary_period' => 'bulan', 'description' => 'Membangun aplikasi yang aman, mudah digunakan, dan dapat diandalkan.', 'qualifications' => 'Menguasai PHP dan Laravel dengan baik.', 'method' => 'EXTERNAL', 'external_url' => 'https://career.example.com/engineer', 'status' => 'DRAFT', 'deadline' => now()->addDays(20)];
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => 'PUBLISHED', 'published_at' => now(), 'expires_at' => now()->addDays(30)]);
    }
}
