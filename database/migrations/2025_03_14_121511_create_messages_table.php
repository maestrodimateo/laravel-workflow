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
        Schema::create('messages', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('subject');
            $table->text('content');
            $table->string('type');
            $table->string('recipient');
            $table->foreignUuid('circuit_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('basket_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
