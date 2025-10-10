<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_chunks', function (Blueprint $table) {
            // This will be set to true once the document is processed and included in the graph building process
            // the flag is set by the graphbuilder service
            $table->boolean('processed')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('document_chunks', function (Blueprint $table) {
            //
        });
    }
};
