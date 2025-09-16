<?php

use App\Models\Document;
use App\Models\Participant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Document::class)->constrained()->cascadeOnDelete();
            $table->longText('body');
            $table->foreignIdFor(Participant::class, 'author_id')->constrained()->cascadeOnDelete();
            $table->dateTime('commented_at');
            $table->string('comment_id');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE document_comments ADD COLUMN embedding vector(768)");

        DB::statement("
          ALTER TABLE document_comments
          ADD COLUMN search_vector tsvector
          GENERATED ALWAYS AS (
              setweight(to_tsvector('english', body), 'A')
          ) STORED
      ");

        DB::statement("CREATE INDEX document_comments_search_vector_idx ON document_comments USING GIN(search_vector)");
        DB::statement("CREATE INDEX document_comments_embedding_idx ON document_comments USING hnsw (embedding vector_cosine_ops)");
    }

    public function down(): void
    {
        Schema::dropIfExists('document_comments');
    }
};
