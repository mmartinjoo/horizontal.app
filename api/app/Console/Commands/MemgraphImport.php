<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\GraphDB\GraphDB;
use Illuminate\Console\Command;

class MemgraphImport extends Command
{
    /** @var string */
    protected $signature = 'memgraph:import {--tenant= : Tenant ID to import}';

    /** @var string */
    protected $description = 'Import Memgraph database from Cypher export file';

    public function handle()
    {
        if (! app()->environment('local')) {
            $this->error('This command can only be run in local environment.');

            return Command::FAILURE;
        }

        $cypherPath = database_path('memgraph/memgraph-export.cypherl');

        if (!file_exists($cypherPath)) {
            $this->error('Cypher export file not found at: ' . $cypherPath);

            return Command::FAILURE;
        }

        // Find and initialize tenant
        $tenantId = $this->option('tenant');

        $tenant = $tenantId
            ? Tenant::query()->find($tenantId)
            : Tenant::query()->first();

        if (!$tenant) {
            $error = $tenantId
                ? "Tenant '{$tenantId}' not found"
                : "No tenants found in database";
            $this->error($error);

            return Command::FAILURE;
        }

        tenancy()->initialize($tenant);

        $this->info("Importing Memgraph data for tenant {$tenant->id}...");
        $this->info('Loading Cypher export file...');

        $cypher = file_get_contents($cypherPath);

        if ($cypher === false) {
            $this->error('Failed to read Cypher export file');

            return Command::FAILURE;
        }

        $this->info('Executing Cypher statements...');

        $graphDB = app(GraphDB::class);

        // Switch to analytical mode and clear existing data
        $graphDB->run('STORAGE MODE IN_MEMORY_ANALYTICAL;');
        $graphDB->run('DROP GRAPH;');

        // Split the export into individual statements and execute each one
        $statements = array_filter(
            explode(';', $cypher),
            fn($stmt) => !empty(trim($stmt))
        );

        $total = count($statements);
        $this->info("Executing {$total} statements...");

        foreach ($statements as $index => $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                $graphDB->run($statement . ';');
                if (($index + 1) % 100 === 0) {
                    $this->info("Progress: " . ($index + 1) . "/{$total}");
                }
            }
        }

        // Switch back to transactional mode
        $graphDB->run('STORAGE MODE IN_MEMORY_TRANSACTIONAL;');

        $this->info('Memgraph data imported successfully from Cypher export');

        return Command::SUCCESS;
    }
}
