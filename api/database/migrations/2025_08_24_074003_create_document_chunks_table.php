<?php

use App\Models\Document;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Document::class)->constrained()->cascadeOnDelete();
            $table->longText('body');
            $table->integer('position');

            $table->timestamps();
        });

        DB::statement("ALTER TABLE document_chunks ADD COLUMN embedding vector(768)");

        DB::statement("
          ALTER TABLE document_chunks
          ADD COLUMN search_vector tsvector
          GENERATED ALWAYS AS (
              setweight(to_tsvector('english', body), 'A')
          ) STORED
      ");

        DB::statement("CREATE INDEX document_chunks_search_vector_idx ON document_chunks USING GIN(search_vector)");
        DB::statement("CREATE INDEX document_chunks_embedding_idx ON document_chunks USING hnsw (embedding vector_cosine_ops)");
    }

    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
    }
};
