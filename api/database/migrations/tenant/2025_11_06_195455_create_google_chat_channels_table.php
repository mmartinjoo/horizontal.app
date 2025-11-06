<?php

use App\Models\GoogleChatIntegration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_chat_channels', function (Blueprint $table) {
            $table->id();
            $table->string('external_id');
            $table->string('name');
            $table->foreignIdFor(GoogleChatIntegration::class)->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_chat_channels');
    }
};
