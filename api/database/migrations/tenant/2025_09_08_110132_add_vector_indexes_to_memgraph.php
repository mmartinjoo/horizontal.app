<?php

use App\Services\GraphDB\GraphDB;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        try {
            $graphDB = app(GraphDB::class);
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
        } catch (Throwable $e) {
            if (Str::contains($e->getMessage(), 'Given vector index already exists.')) {
                // this can happen in local or testing environment where we have a fixed number of GraphDB
                // instances and they are not re-created for each tenant so indexes will exist
                return;
            }
            throw $e;
        }
    }

    public function down()
    {
        $graphDB = app(GraphDB::class);
        $graphDB->run("DROP VECTOR INDEX vector_index_entity");
        $graphDB->run("DROP VECTOR INDEX vector_index_communities");
    }
};
