<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE document_chunks
            ADD COLUMN embedding vector(1536)
        ");

        DB::statement("
            CREATE INDEX document_chunks_embedding_cosine_idx
            ON document_chunks
            USING ivfflat (embedding vector_cosine_ops)
            WITH (lists = 100)
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS document_chunks_embedding_cosine_idx");

        Schema::table('document_chunks', function (Blueprint $table) {
            $table->dropColumn('embedding');
        });
    }
};
