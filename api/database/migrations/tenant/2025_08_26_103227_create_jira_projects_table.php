<?php

use App\Models\JiraIntegration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jira_projects', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('key');
            $table->integer('jira_id');
            $table->foreignIdFor(JiraIntegration::class)->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique('key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jira_projects');
    }
};
