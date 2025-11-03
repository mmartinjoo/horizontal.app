<?php

namespace App\Http\Controllers\ElasticMemgraphService;

use App\Exceptions\ElasticMemgraphService\MemgraphInstanceAlreadyOcupied;
use App\Exceptions\ElasticMemgraphService\NoAvailableInstances;
use App\Models\Tenant;
use App\Services\ElasticMemgraphService\ElasticMemgraphService;
use Symfony\Component\HttpFoundation\Response;

class OccupationController
{
    public function occupy(string $tenantId, ElasticMemgraphService $ems)
    {
        try {
            $tenant = Tenant::findOrFail($tenantId);
            $instance = $ems->findAvailableInstance();
            $ems->occupy($instance, $tenant);

            return response()->json($instance, Response::HTTP_CREATED);
        } catch (NoAvailableInstances | MemgraphInstanceAlreadyOcupied $ex) {
            return response()->json([
                'error' => $ex->getMessage(),
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }
}