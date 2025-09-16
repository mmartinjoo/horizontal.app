<?php

use App\Models\JiraIntegration;
use App\Models\Team;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jira_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Team::class)->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('key');
            $table->integer('jira_id');
            $table->foreignIdFor(JiraIntegration::class)->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['team_id', 'key']);
        });

        DB::statement("ALTER TABLE jira_projects ADD COLUMN embedding vector(768)");
        DB::statement("CREATE INDEX jira_projects_embedding_idx ON jira_projects USING hnsw (embedding vector_cosine_ops)");
    }

    public function down(): void
    {
        Schema::dropIfExists('jira_projects');
    }
};
