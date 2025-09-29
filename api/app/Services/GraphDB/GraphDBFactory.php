<?php

namespace App\Services\GraphDB;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Exceptions\TenancyNotInitializedException;

class GraphDBFactory
{
    public static function create(): GraphDB
    {
        /** @var TenantWithDatabase $tenant */
        $tenant = tenant();
        if (!$tenant) {
            throw new TenancyNotInitializedException("Tenant is not initialized for creating a GraphDB instance");
        }

        config([
            'graphdb.connections.memgraph' => [
                'host' => $tenant->graph_db_host,
                'port' => $tenant->graph_db_port,
                'user' => $tenant->graph_db_user,
                'password' => decrypt($tenant->graph_db_password),
                'scheme' => $tenant->graph_db_scheme,
            ],
        ]);
        return new Memgraph(config('graphdb.connections.memgraph'));
    }
}
