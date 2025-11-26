<?php

use App\Enums\ElasticMemgraphService\MemgraphInstanceStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memgraph_instances', function (Blueprint $table) {
            $table->id();
            $table->string('host');
            $table->integer('port');
            $table->string('username');
            $table->text('password_encrypted');            
            $table->string('db_schema');
            $table->string('status')->default(MemgraphInstanceStatus::Available->value);
            $table->string('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->dateTime('occupied_at')->nullable();
            $table->timestamps();            
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memgraph_instances');
    }
};
