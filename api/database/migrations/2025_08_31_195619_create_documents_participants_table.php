<?php

use App\Models\Participant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents_participants', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->foreignIdFor(Participant::class)->constrained()->cascadeOnDelete();
            $table->string('context')->nullable();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE documents_participants ADD COLUMN embedding vector(768)");
        DB::statement("CREATE INDEX documents_participants_embedding_idx ON documents_participants USING hnsw (embedding vector_cosine_ops)");
    }

    public function down(): void
    {
        Schema::dropIfExists('documents_participants');
    }
};
