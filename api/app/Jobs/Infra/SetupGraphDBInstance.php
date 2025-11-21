<?php

namespace App\Jobs\Infra;

use App\Services\ElasticMemgraphService\ElasticMemgraphService;
use App\Services\GraphDB\GraphDBFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Throwable;

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

        try {
            $graphDB = GraphDBFactory::create();
            $graphDB->run('CREATE INDEX ON :__Node__(id);');
            $graphDB->run('CREATE INDEX ON :__Entity__(id);');
            $graphDB->run("
                CREATE VECTOR INDEX vector_index_entity
                ON :__Entity__(embedding)
                WITH CONFIG {\"dimension\": 768, \"capacity\": 20000};"
            );
            $graphDB->run("
                CREATE VECTOR INDEX vector_index_communities
                ON :Community(embedding)
                WITH CONFIG {\"dimension\": 768, \"capacity\": 20000}
            ");
        } catch (Throwable $ex) {
            if (Str::contains($ex->getMessage(), 'already exists') || Str::contains($ex->getMessage(), 'IndexDefinitionAlreadyExist')) {
                logger()->info('index already existed');
                return;
            }

            throw $ex;
        }
    }
}
