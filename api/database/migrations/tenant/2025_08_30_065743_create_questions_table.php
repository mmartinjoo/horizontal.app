<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->string('question');
            $table->jsonb('potentially_relevant_documents')->nullable(true)->default(null);
            $table->jsonb('relevant_documents')->nullable(true)->default(null);
            $table->jsonb('relevant_graph_paths')->nullable(true)->default(null);
            $table->text('answer')->nullable(true)->default(null);
            $table->dateTime('answered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
