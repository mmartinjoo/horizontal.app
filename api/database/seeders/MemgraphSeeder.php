<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class MemgraphSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $this->command->info("Seeding Memgraph for tenant {$tenant->id}...");
            Artisan::call('memgraph:seed', ['--tenant' => $tenant->id]);
            $this->command->info(Artisan::output());
        }
    }
}
