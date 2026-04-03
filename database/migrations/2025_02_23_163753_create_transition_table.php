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
        Schema::create('transition', static function (Blueprint $table): void {
            $table->primary(['from_basket_id', 'to_basket_id']);
            $table->foreignUuid('from_basket_id')->constrained('baskets', 'id')->cascadeOnDelete();
            $table->foreignUuid('to_basket_id')->constrained('baskets', 'id')->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->json('actions')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transition');
    }
};
