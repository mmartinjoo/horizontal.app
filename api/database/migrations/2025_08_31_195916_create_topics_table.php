<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topics', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->jsonb('variations')->nullable();
            $table->string('category');
            $table->timestamps();
        });

        DB::statement("ALTER TABLE topics ADD COLUMN embedding vector(768)");

        DB::statement("
          ALTER TABLE topics
          ADD COLUMN search_vector tsvector
          GENERATED ALWAYS AS (
              setweight(to_tsvector('english', name), 'A') ||
              setweight(to_tsvector('english', variations), 'B') ||
              setweight(to_tsvector('english', category), 'C')
          ) STORED
      ");

        DB::statement("CREATE INDEX topics_search_vector_idx ON topics USING GIN(search_vector)");
        DB::statement("CREATE INDEX topics_embedding_idx ON topics USING hnsw (embedding vector_cosine_ops)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('topics');
    }
};
