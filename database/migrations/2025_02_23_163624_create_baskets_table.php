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
        Schema::create('baskets', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unique(['status', 'circuit_id']);
            $table->string('name');
            $table->string('status');
            $table->string('color')->default('#2563eb');
            $table->json('roles')->nullable();
            $table->foreignUuid('circuit_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('baskets');
    }
};
