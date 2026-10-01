<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([Role::SEEKER, Role::RECRUITER, Role::ADMIN] as $role) {
            Role::firstOrCreate(['name' => $role]);
        }
    }
}
