<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;

class TestCommand extends Command
{
    protected $signature = 'app:test';

    protected $description = 'Command description';

    public function handle()
    {
        $tenant1 = Tenant::create();
        $tenant1->createDomain('tenant1.localhost');
        tenancy()->initialize($tenant1);
        User::create([
            'name' => 'Tenant1 User',
            'email' => 'user@tenant1.com',
            'password' => bcrypt('password'),
        ]);

        $tenant2 = Tenant::create();
        $tenant2->createDomain('tenant2.localhost');
        tenancy()->initialize($tenant2);
        User::create([
            'name' => 'Tenant2 User',
            'email' => 'user@tenant2.com',
            'password' => bcrypt('password'),
        ]);

        tenancy()->end();
        User::create([
            'name' => 'Admin',
            'email' => 'admin@horizontal.app',
            'password' => bcrypt('password'),
        ]);
    }
}
