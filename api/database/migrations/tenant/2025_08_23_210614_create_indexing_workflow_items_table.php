<?php

use App\Models\Document;
use App\Models\IndexingWorkflow;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indexing_workflow_items', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(IndexingWorkflow::class)->constrained()->cascadeOnDelete();
            $table->jsonb('data');
            $table->string('status');
            $table->foreignIdFor(Document::class)->nullable()->constrained()->cascadeOnDelete();
            $table->text('error_message')->nullable();
            $table->jsonb('job_ids')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indexing_workflow_items');
    }
};
