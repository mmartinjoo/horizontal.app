<?php

use App\Models\Document;
use App\Models\Participant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_worklogs', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Document::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Participant::class, 'author_id')->constrained()->cascadeOnDelete();
            $table->string('description')->nullable();
            $table->dateTime('logged_at');
            $table->string('worklog_id');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE document_worklogs ADD COLUMN embedding vector(768)");

        DB::statement("
          ALTER TABLE document_worklogs
          ADD COLUMN search_vector tsvector
          GENERATED ALWAYS AS (
              setweight(to_tsvector('english', description), 'A')
          ) STORED
      ");

        DB::statement("CREATE INDEX document_worklogs_search_vector_idx ON document_worklogs USING GIN(search_vector)");
        DB::statement("CREATE INDEX document_worklogs_embedding_idx ON document_worklogs USING hnsw (embedding vector_cosine_ops)");
    }

    public function down(): void
    {
        Schema::dropIfExists('document_worklogs');
    }
};
