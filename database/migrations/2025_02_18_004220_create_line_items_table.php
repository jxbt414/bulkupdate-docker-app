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
        Schema::create('line_items', function (Blueprint $table) {
            $table->id();
            $table->string('line_item_id')->unique();
            $table->string('line_item_name');
            $table->decimal('budget', 10, 2)->nullable();
            $table->integer('priority')->nullable();
            $table->json('impression_goals')->nullable();
            $table->json('targeting')->nullable();
            $table->json('labels')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('line_items');
    }
};
