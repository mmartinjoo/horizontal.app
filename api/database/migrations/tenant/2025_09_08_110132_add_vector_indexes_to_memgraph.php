<?php

use App\Services\GraphDB\GraphDB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
//        $graphDB = app(GraphDB::class);
//        $graphDB->run("
//            CREATE VECTOR INDEX vector_index_entity
//            ON :__Entity__(embedding)
//            WITH CONFIG {\"dimension\": 768, \"capacity\": 20000};"
//        );
//        $graphDB->run("
//            CREATE VECTOR INDEX vector_index_communities
//            ON :Community(embedding)
//            WITH CONFIG {\"dimension\": 768, \"capacity\": 20000}
//        ");
    }

    public function down()
    {
        $graphDB = app(GraphDB::class);
        $graphDB->run("DROP VECTOR INDEX vector_index_entity");
        $graphDB->run("DROP VECTOR INDEX vector_index_communities");
    }
};
