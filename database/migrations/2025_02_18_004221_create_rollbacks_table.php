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
        Schema::create('rollbacks', function (Blueprint $table) {
            $table->id();
            $table->string('line_item_id');
            $table->json('previous_data');
            $table->timestamp('rollback_timestamp');
            $table->timestamps();

            $table->foreign('line_item_id')
                ->references('line_item_id')
                ->on('line_items')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rollbacks');
    }
};
