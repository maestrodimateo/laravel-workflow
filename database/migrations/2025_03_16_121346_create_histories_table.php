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
        Schema::create('histories', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('previous_status');
            $table->string('next_status');
            $table->text('comment')->nullable();
            $table->uuidMorphs('historable');
            $table->string('done_by');
            $table->unsignedBigInteger('duration_seconds')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('histories');
    }
};
