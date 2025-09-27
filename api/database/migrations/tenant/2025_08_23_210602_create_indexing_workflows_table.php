<?php

use App\Models\Team;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indexing_workflows', function (Blueprint $table) {
            $table->id();
            $table->string('integration');
            $table->integer('overall_items')->default(0);
            $table->string('status');
            $table->foreignIdFor(Team::class)->constrained()->cascadeOnDelete();
            $table->string('job_id')->nullable();
            $table->integer('deleted_items')->default(0);
            $table->integer('skipped_items')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indexing_workflows');
    }
};
