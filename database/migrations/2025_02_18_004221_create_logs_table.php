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
        Schema::create('logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('action');
            $table->text('description');
            $table->string('line_item_id')->nullable();
            $table->string('status')->default('success');
            $table->text('error_message')->nullable();
            $table->string('batch_id')->nullable();
            $table->string('type')->nullable();
            $table->timestamps();

            $table->foreign('line_item_id')
                ->references('line_item_id')
                ->on('line_items')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logs');
    }
};
