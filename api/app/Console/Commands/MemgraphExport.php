<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\GraphDB\GraphDB;
use Illuminate\Console\Command;

class MemgraphExport extends Command
{
    /** @var string */
    protected $signature = 'memgraph:export {--tenant= : Tenant ID to export}';

    /** @var string */
    protected $description = 'Export Memgraph database to Cypher file';

    public function handle()
    {
        if (! app()->environment('local')) {
            $this->error('This command can only be run in local environment.');

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

        $this->info("Exporting Memgraph data for tenant {$tenant->id}...");

        $graphDB = app(GraphDB::class);

        // Export the database using Memgraph's dump command
        $this->info('Generating Cypher export...');

        // Get all nodes and relationships
        $result = $graphDB->run('DUMP DATABASE;');

        $cypherPath = database_path('memgraph/memgraph-export.cypherl');
        $directory = dirname($cypherPath);

        // Ensure directory exists
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // Write the export to file
        $statements = [];
        foreach ($result as $record) {
            // Each record is an associative array with 'QUERY' key (uppercase)
            // The QUERY already ends with a semicolon
            if (isset($record['QUERY'])) {
                $statements[] = rtrim($record['QUERY'], ';');
            }
        }

        $cypher = implode(";\n", $statements) . ';';

        if (file_put_contents($cypherPath, $cypher) === false) {
            $this->error('Failed to write Cypher export file');

            return Command::FAILURE;
        }

        $this->info("Memgraph data exported successfully to: {$cypherPath}");
        $this->info('Total statements: ' . count($statements));

        return Command::SUCCESS;
    }
}
