<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->string('type')->default('person');
            $table->timestamps();
        });

        DB::statement("ALTER TABLE participants ADD COLUMN embedding vector(768)");
        DB::statement("CREATE INDEX participants_embedding_idx ON participants USING hnsw (embedding vector_cosine_ops)");
    }

    public function down(): void
    {
        Schema::dropIfExists('participants');
    }
};
