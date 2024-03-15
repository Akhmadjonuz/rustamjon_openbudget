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
        Schema::create('send', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('pending');
            $table->string('message')->nullable();
            $table->string('video')->nullable();
            $table->string('photos')->default('[]');
            $table->string('forward_from_chat_id')->nullable();
            $table->integer('forward_from_message_id')->nullable();
            $table->string('type')->nullable();
            $table->bigInteger('last_count')->default(0);
            $table->integer('limit')->default(200);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('send');
    }
};
