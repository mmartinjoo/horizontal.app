<?php

use App\Models\LinearIntegration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('linear_projects', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->integer('external_id');
            $table->foreignIdFor(LinearIntegration::class)->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique('external_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('linear_projects');
    }
};
