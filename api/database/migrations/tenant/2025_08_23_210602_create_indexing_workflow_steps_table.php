<?php

use App\Models\IndexingWorkflow;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indexing_workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(IndexingWorkflow::class)->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('status');            
            $table->integer('overall_items')->default(0);
            $table->integer('processed_items')->default(0);
            $table->integer('deleted_items')->default(0);
            $table->integer('skipped_items')->default(0);            
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->string('service');
            $table->string('job_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indexing_workflow_steps');
    }
};
