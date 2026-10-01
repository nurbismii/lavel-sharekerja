<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return ['owner_user_id' => User::factory(), 'name' => fake()->company(), 'slug' => fake()->unique()->slug(), 'industry' => 'Teknologi', 'email' => fake()->companyEmail(), 'phone' => '081234567890', 'position' => 'HR Manager', 'location' => 'Makassar', 'description' => 'Perusahaan pengembangan produk digital dan teknologi.', 'verification_status' => 'VERIFIED', 'status' => 'ACTIVE'];
    }
}
