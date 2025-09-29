<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('jira_integrations', function (Blueprint $table) {
            $table->string('cloud_id')->nullable()->after('jira_base_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jira_integrations', function (Blueprint $table) {
            $table->dropColumn('cloud_id');
        });
    }
};
