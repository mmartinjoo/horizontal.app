<?php

namespace App\Http\Controllers;

use App\Models\Tenant;

class TenantController
{
    public function show(Tenant $tenant)
    {
        return [
            'id' => $tenant->id,
            'company' => $tenant->company,
            'graph_db_connection' => [
                'host' => $tenant->graph_db_host,
                'port' => $tenant->graph_db_port,
                'user' => $tenant->graph_db_user,
                'password' => decrypt($tenant->graph_db_password),
                'scheme' => $tenant->graph_db_scheme,
            ],
            'domains' => $tenant->domains->pluck('domain'),
        ];
    }
}
