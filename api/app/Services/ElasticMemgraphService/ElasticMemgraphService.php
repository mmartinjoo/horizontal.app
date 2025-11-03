<?php

namespace App\Services\ElasticMemgraphService;

use App\Enums\ElasticMemgraphService\MemgraphInstanceStatus;
use App\Exceptions\ElasticMemgraphService\MemgraphInstanceAlreadyOcupied;
use App\Exceptions\ElasticMemgraphService\NoAvailableInstances;
use App\Models\ElasticMemgraphService\MemgraphInstance;
use App\Models\Tenant;

class ElasticMemgraphService
{
    public function occupy(MemgraphInstance $memgraphInstance, Tenant $tenant)
    {
        if ($this->hasOccupation($tenant)) {
            throw new MemgraphInstanceAlreadyOcupied('tenant already has a Memgraph instance occupied');
        }

        $memgraphInstance->update([
            'tenant_id' => $tenant->id,
            'occupied_at' => now(),
            'status' => MemgraphInstanceStatus::Occupied->value,
        ]);
    }

    public function findAvailableInstance(): MemgraphInstance
    {
        $instance = MemgraphInstance::query()
            ->where('status', MemgraphInstanceStatus::Available->value)
            ->whereNull('tenant_id')
            ->first();

        if (!$instance) {
            throw new NoAvailableInstances('there are no available Memgraph instances');
        }

        return $instance;
    }

    private function hasOccupation(Tenant $tenant): bool
    {
        return MemgraphInstance::query()
            ->where('tenant_id', $tenant->id)
            ->exists();
    }
}