<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantsTable extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->string('id')->primary();

            $table->string('company');
            $table->string('country');

            $table->string('graph_db_host')->nullable();
            $table->integer('graph_db_port')->nullable();
            $table->string('graph_db_user')->nullable();
            $table->string('graph_db_password')->nullable();
            $table->string('graph_db_scheme')->nullable();

            $table->json('data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
}
