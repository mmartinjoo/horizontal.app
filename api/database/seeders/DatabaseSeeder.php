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

        $tenant1 = Tenant::create([
            'company' => 'Tenant1 Ltd.',
            'country' => 'US',
        ]);
        $tenant1->createDomain('tenant1.localhost');
        tenancy()->initialize($tenant1);
        User::create([
            'name' => 'Tenant1 User',
            'email' => 'user@tenant1.com',
            'password' => bcrypt('password'),
        ]);

        $tenant2 = Tenant::create([
            'company' => 'Tenant2 Ltd.',
            'country' => 'US',
        ]);
        $tenant2->createDomain('tenant2.localhost');
        tenancy()->initialize($tenant2);
        User::create([
            'name' => 'Tenant2 User',
            'email' => 'user@tenant2.com',
            'password' => bcrypt('password'),
        ]);

        $this->call(MemgraphSeeder::class);
    }
}
