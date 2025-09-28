<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@horizontal.app',
        ]);

        $tenant1 = Tenant::create();
        $tenant1->createDomain('tenant1.localhost');
        tenancy()->initialize($tenant1);
        User::create([
            'name' => 'Tenant1 User',
            'email' => 'user@tenant1.com',
            'password' => bcrypt('password'),
        ]);
    }
}
