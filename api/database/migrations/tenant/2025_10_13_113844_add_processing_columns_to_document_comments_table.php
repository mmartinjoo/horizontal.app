<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_comments', function (Blueprint $table) {
            // All of these are managed by the graphbuilder service
            $table->dateTime('processing_started_at')->nullable();
            $table->dateTime('processing_finished_at')->nullable();
            $table->string('processing_job_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('document_comments', function (Blueprint $table) {
            $table->dropColumn('processing_started_at');
            $table->dropColumn('processing_finished_at');
            $table->dropColumn('processing_job_id');
        });
    }
};
