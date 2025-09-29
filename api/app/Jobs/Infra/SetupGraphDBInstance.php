<?php

namespace App\Jobs\Infra;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Stancl\Tenancy\Contracts\TenantWithDatabase;

class SetupGraphDBInstance implements ShouldQueue
{
    use Queueable;

    protected TenantWithDatabase $tenant;

    public function __construct(TenantWithDatabase $tenant)
    {
        $this->tenant = $tenant;
    }

    public function handle(): void
    {
        if (config('app.env') === 'local') {
            $graphDBHost = 'memgraph-tenant1';
            if (Str::contains(Str::lower($this->tenant->company), 'tenant2')) {
                $graphDBHost = 'memgraph-tenant2';
            }
            $this->tenant->update([
                'graph_db_host' => $graphDBHost,
                'graph_db_port' => 7687,
                'graph_db_user' => 'horizontal',
                'graph_db_password' => encrypt('password'),
                'graph_db_scheme' => 'basic',
            ]);
        }
        // TODO: Schedule new ECS task in prod
    }
}
