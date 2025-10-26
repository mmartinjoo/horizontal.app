<?php

use App\Models\IndexingWorkflowStep;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indexing_workflow_step_buckets', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(IndexingWorkflowStep::class)->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('status');
            $table->integer('overall_items')->default(0);
            $table->integer('processed_items')->default(0);
            $table->integer('deleted_items')->default(0);
            $table->integer('skipped_items')->default(0);            
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('indexing_workflow_step_buckets');
    }
};
