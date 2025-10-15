<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class MemgraphSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $this->command->info("Seeding Memgraph for tenant {$tenant->id}...");

            Artisan::call('memgraph:import', ['--tenant' => $tenant->id]);

            $this->command->info(Artisan::output());
        }
    }
}
