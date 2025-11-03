<?php

namespace App\Jobs\Infra;

use App\Services\ElasticMemgraphService\ElasticMemgraphService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Stancl\Tenancy\Contracts\TenantWithDatabase;

class SetupGraphDBInstance implements ShouldQueue
{
    use Queueable;

    protected TenantWithDatabase $tenant;

    public function __construct(TenantWithDatabase $tenant)
    {
        $this->tenant = $tenant;
    }

    public function handle(ElasticMemgraphService $ems): void
    {
        tenancy()->central(function () use ($ems) {
            $instance = $ems->findAvailableInstance();
            $ems->occupy($instance, $this->tenant);

            $this->tenant->update([
                'graph_db_host' => $instance->host,
                'graph_db_port' => $instance->port,
                'graph_db_user' => $instance->username,
                'graph_db_password' => $instance->password_encrypted,
                'graph_db_scheme' => $instance->db_schema,
            ]);
        });
    }
}
